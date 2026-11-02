<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Validates and persists the typed values and option selections for one Product. */
class ProductAttributeValueManager
{
    /** @param array<string|int, mixed> $values */
    public function save(Product $product, array $values, array $selectedAttributeIds, string $targetStatus): void
    {
        $assignments = $product->productType()->firstOrFail()->attributes()->with(['options'])->get()->keyBy('id');
        $existing = $product->attributeValues()->with('selections')->get()->keyBy('attribute_id');
        $previouslySelected = $product->selectedAttributes()->pluck('attributes.id')->map(fn ($id): int => (int) $id)->all();
        // Values predating the assignment table are selected by definition; never infer
        // deselection from the absence of a new pivot row.
        $previouslySelected = array_values(array_unique([...$previouslySelected, ...$existing->keys()->map(fn ($id): int => (int) $id)->all()]));
        $selected = [];
        foreach ($selectedAttributeIds as $attributeId) {
            $attributeId = filter_var($attributeId, FILTER_VALIDATE_INT);
            if ($attributeId === false || ! $assignments->has($attributeId) || (bool) $assignments->get($attributeId)->pivot->is_required) {
                throw ValidationException::withMessages(['selected_attribute_ids' => 'Choose only optional attributes assigned to the selected Product Type.']);
            }
            $attribute = $assignments->get($attributeId);
            if (! $attribute->is_active && ! in_array((int) $attributeId, $previouslySelected, true)) {
                throw ValidationException::withMessages(['selected_attribute_ids' => 'Inactive attributes cannot be newly selected.']);
            }
            $selected[] = (int) $attributeId;
        }
        $selected = array_values(array_unique($selected));
        foreach ($previouslySelected as $attributeId) {
            if (! $assignments->has($attributeId) || (bool) $assignments->get($attributeId)->pivot->is_required || in_array($attributeId, $selected, true)) {
                continue;
            }
            if ($existing->has($attributeId)) {
                $value = $existing->get($attributeId);
                if (! $this->isEmptyForRecord($value)) {
                    throw ValidationException::withMessages(['selected_attribute_ids' => "{$assignments->get($attributeId)->name} has a saved value. Clear it explicitly before removing this attribute."]);
                }
            }
        }
        $normalized = [];

        foreach ($values as $attributeId => $value) {
            $attributeId = filter_var($attributeId, FILTER_VALIDATE_INT);
            if ($attributeId === false || ! $assignments->has($attributeId)) {
                throw ValidationException::withMessages(["attribute_values.{$attributeId}" => 'This attribute is not assigned to the selected Product Type.']);
            }

            $attribute = $assignments->get($attributeId);
            if (! (bool) $attribute->pivot->is_required && ! in_array((int) $attribute->id, $selected, true) && ! in_array((int) $attribute->id, $previouslySelected, true)) {
                throw ValidationException::withMessages(["attribute_values.{$attributeId}" => 'Select this optional attribute before entering a value.']);
            }
            if (! $attribute->is_active && ! $existing->has($attribute->id)) {
                throw ValidationException::withMessages(["attribute_values.{$attributeId}" => 'Inactive attributes cannot receive new values.']);
            }
            $normalized[$attribute->id] = $this->normalize($attribute, $value, $existing->get($attribute->id));
        }

        foreach ($assignments as $attribute) {
            if (! $attribute->is_active && ! $existing->has($attribute->id)) {
                continue;
            }
            $value = $normalized[$attribute->id] ?? null;
            if ((bool) $attribute->pivot->is_required && $targetStatus === 'published' && $this->isEmpty($attribute->type, $value)) {
                throw ValidationException::withMessages(["attribute_values.{$attribute->id}" => "{$attribute->name} is required before this product can be published."]);
            }
        }

        // Persistence is intentionally performed by ProductCategoryManager inside its transaction.
        $product->setRelation('_pending_attribute_values', collect($normalized));
        $product->setRelation('_pending_selected_attribute_ids', collect($selected));
    }

    /** Persist the previously validated form values while the Product transaction is open. */
    public function persist(Product $product): void
    {
        $values = $product->getRelation('_pending_attribute_values') ?? collect();
        $selected = $product->getRelation('_pending_selected_attribute_ids') ?? collect();
        $attributes = $product->productType()->firstOrFail()->attributes()->get()->keyBy('id');
        $existing = $product->attributeValues()->with('selections')->get()->keyBy('attribute_id');

        foreach ($attributes as $attribute) {
            $value = $values->get($attribute->id);
            if (! $values->has($attribute->id)) {
                $row = $existing->get($attribute->id);
                if (! (bool) $attribute->pivot->is_required && ! $selected->contains((int) $attribute->id) && $row && $this->isEmptyForRecord($row)) {
                    $row->delete();
                }

                continue;
            }

            $row = $existing->get($attribute->id);
            if ($this->isEmpty($attribute->type, $value)) {
                $row?->delete();

                continue;
            }

            $row ??= new ProductAttributeValue([
                'product_id' => $product->id,
                'product_type_id' => $product->product_type_id,
                'attribute_id' => $attribute->id,
            ]);
            $row->text_value = $attribute->type === 'text' ? $value : null;
            $row->number_value = $attribute->type === 'number' ? $value : null;
            $row->boolean_value = $attribute->type === 'boolean' ? $value : null;
            $row->save();

            $optionIds = in_array($attribute->type, ['select', 'multiselect'], true) ? (array) $value : [];
            $row->selections()->whereNotIn('attribute_option_id', $optionIds)->delete();
            foreach ($optionIds as $optionId) {
                $row->selections()->firstOrCreate([
                    'attribute_id' => $attribute->id,
                    'attribute_option_id' => $optionId,
                ]);
            }
        }
        $product->selectedAttributes()->sync($selected->all());
        $product->unsetRelation('_pending_attribute_values');
        $product->unsetRelation('_pending_selected_attribute_ids');
    }

    private function isEmptyForRecord(ProductAttributeValue $value): bool
    {
        return $value->text_value === null && $value->number_value === null && $value->boolean_value === null && ! $value->selections->isNotEmpty();
    }

    private function normalize(Attribute $attribute, mixed $value, ?ProductAttributeValue $existing): mixed
    {
        $path = "attribute_values.{$attribute->id}";
        if ($value === '' || $value === null || $value === []) {
            return null;
        }

        if ($attribute->type === 'text') {
            $validator = Validator::make(['value' => $value], ['value' => ['string', 'max:10000']]);
            $this->throwIfInvalid($validator, $path);

            $value = trim($value);

            return $value === '' ? null : $value;
        }
        if ($attribute->type === 'number') {
            $validator = Validator::make(['value' => $value], ['value' => ['numeric', 'decimal:0,6', 'between:-99999999999999.999999,99999999999999.999999']]);
            $this->throwIfInvalid($validator, $path);

            return (string) $value;
        }
        if ($attribute->type === 'boolean') {
            $validator = Validator::make(['value' => $value], ['value' => ['boolean']]);
            $this->throwIfInvalid($validator, $path);

            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        $optionIds = $attribute->type === 'select' ? [$value] : $value;
        $rules = ['value' => ['array'], 'value.*' => ['required', 'integer', 'distinct', 'exists:attribute_options,id']];
        if ($attribute->type === 'select') {
            $rules['value'][] = 'size:1';
        }
        $validator = Validator::make(['value' => $optionIds], $rules);
        $this->throwIfInvalid($validator, $path);
        $optionIds = array_map('intval', $optionIds);
        if (count($optionIds) !== count(array_unique($optionIds))) {
            throw ValidationException::withMessages([$path => 'Duplicate options are not allowed.']);
        }
        $options = $attribute->options->keyBy('id')->only($optionIds);
        if ($options->count() !== count($optionIds)) {
            throw ValidationException::withMessages([$path => 'Every selected option must belong to this attribute.']);
        }
        $previous = $existing?->selections->pluck('attribute_option_id')->map(fn ($id): int => (int) $id)->all() ?? [];
        foreach ($options as $option) {
            if (! $option->is_active && ! in_array($option->id, $previous, true)) {
                throw ValidationException::withMessages([$path => 'Inactive options cannot be newly selected.']);
            }
        }

        return $attribute->type === 'select' ? $optionIds[0] : $optionIds;
    }

    private function isEmpty(string $type, mixed $value): bool
    {
        return $value === null || ($type === 'multiselect' && $value === []);
    }

    private function throwIfInvalid(\Illuminate\Contracts\Validation\Validator $validator, string $path): void
    {
        if ($validator->fails()) {
            throw ValidationException::withMessages([$path => $validator->errors()->first('value')]);
        }
    }
}
