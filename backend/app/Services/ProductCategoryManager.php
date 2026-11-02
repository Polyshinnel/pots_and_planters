<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Transactional write path for a Product and its category membership. */
class ProductCategoryManager
{
    /** @param array<string, mixed> $attributes */
    public function save(array $attributes, ?Product $product = null): Product
    {
        $attributeValues = $attributes['attribute_values'] ?? [];
        $selectedAttributeIds = $attributes['selected_attribute_ids'] ?? [];
        $displayRows = $attributes['card_display_attribute_ids'] ?? [];
        unset($attributes['attribute_values']);
        unset($attributes['selected_attribute_ids']);
        unset($attributes['card_display_attribute_ids']);
        if (! is_array($attributeValues)) {
            throw ValidationException::withMessages(['attribute_values' => 'Attribute values must be submitted as a map.']);
        }
        if (! is_array($selectedAttributeIds)) {
            throw ValidationException::withMessages(['selected_attribute_ids' => 'Selected attributes must be submitted as a list.']);
        }
        if (! is_array($displayRows)) {
            throw ValidationException::withMessages(['card_display_attribute_ids' => 'Card display attributes must be submitted as an ordered list.']);
        }
        $displayIds = [];
        foreach ($displayRows as $row) {
            $id = is_array($row) ? ($row['attribute_id'] ?? null) : $row;
            if (filter_var($id, FILTER_VALIDATE_INT) === false) {
                throw ValidationException::withMessages(['card_display_attribute_ids' => 'Choose valid attributes for the product card.']);
            }
            $displayIds[] = (int) $id;
        }
        if (count($displayIds) !== count(array_unique($displayIds))) {
            throw ValidationException::withMessages(['card_display_attribute_ids' => 'An attribute can appear only once in the card display order.']);
        }
        $categoryIds = $attributes['category_ids'] ?? [];
        $primaryCategoryId = $attributes['primary_category_id'] ?? null;
        unset($attributes['category_ids'], $attributes['primary_category_id']);

        $validator = Validator::make([
            'category_ids' => $categoryIds,
            'primary_category_id' => $primaryCategoryId,
        ], [
            'category_ids' => ['present', 'array'],
            'category_ids.*' => ['required', 'integer', 'distinct', 'exists:categories,id'],
            'primary_category_id' => ['nullable', 'integer', Rule::in(array_map('intval', $categoryIds))],
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));
        sort($categoryIds);
        $primaryCategoryId = $primaryCategoryId === null || $primaryCategoryId === '' ? null : (int) $primaryCategoryId;

        return DB::transaction(function () use ($attributes, $attributeValues, $selectedAttributeIds, $displayIds, $categoryIds, $primaryCategoryId, $product): Product {
            if ($categoryIds !== []) {
                $categories = Category::query()->whereKey($categoryIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                if ($categories->count() !== count($categoryIds)) {
                    throw ValidationException::withMessages(['category_ids' => 'One or more selected categories no longer exist.']);
                }
            }

            $record = $product
                ? Product::query()->lockForUpdate()->findOrFail($product->getKey())
                : new Product;
            $oldCanonical = $record->exists ? app(ProductUrlResolver::class)->canonicalPath($record) : null;
            if ($record->exists && (int) $record->product_type_id !== (int) ($attributes['product_type_id'] ?? $record->product_type_id) && ProductAttributeValue::query()->where('product_id', $record->getKey())->exists()) {
                throw ValidationException::withMessages(['product_type_id' => 'This product has saved attribute values. Move or clear them explicitly before changing Product Type.']);
            }
            if ($record->exists && (int) $record->product_type_id !== (int) ($attributes['product_type_id'] ?? $record->product_type_id) && DB::table('product_attribute_assignments')->where('product_id', $record->getKey())->exists()) {
                throw ValidationException::withMessages(['product_type_id' => 'This product has selected optional attributes. Remove those selections and save before changing Product Type.']);
            }
            $oldIds = $record->exists ? $record->categories()->pluck('categories.id')->map(fn ($id): int => (int) $id)->all() : [];
            $oldIdSet = array_flip($oldIds);

            foreach ($categoryIds as $categoryId) {
                $category = $categories[$categoryId];
                if ($category->status !== 'published' && ! isset($oldIdSet[$categoryId])) {
                    throw ValidationException::withMessages(['category_ids' => 'New products may only be assigned to published categories. Existing archived or draft assignments can be retained.']);
                }
            }

            $targetStatus = $attributes['status'] ?? ($record->status ?: 'draft');
            if ($targetStatus === 'published' && ($categoryIds === [] || $primaryCategoryId === null)) {
                throw ValidationException::withMessages(['primary_category_id' => 'A published product must have at least one category and a primary category.']);
            }
            if ($primaryCategoryId !== null && ! in_array($primaryCategoryId, $categoryIds, true)) {
                throw ValidationException::withMessages(['primary_category_id' => 'The primary category must be one of the selected categories.']);
            }

            // First move through a valid draft state, then clear the old primary before
            // changing memberships. All intermediate states remain inside this transaction.
            if ($record->exists) {
                DB::table('products')->where('id', $record->getKey())->update(['status' => 'draft', 'primary_category_id' => null]);
                $record->status = 'draft';
                $record->primary_category_id = null;
            }
            $record->fill($attributes);
            app(ProductAttributeValueManager::class)->save($record, $attributeValues, $selectedAttributeIds, $targetStatus);
            $allowedIds = $record->productType()->firstOrFail()->attributes()->withPivot('is_required')->get()
                ->filter(fn ($attribute): bool => (bool) $attribute->pivot->is_required
                    || in_array((int) $attribute->id, array_map('intval', $selectedAttributeIds), true)
                    || $record->attributeValues()->where('attribute_id', $attribute->id)->exists())
                ->pluck('id')->map(fn ($id): int => (int) $id)->all();
            if (array_diff($displayIds, $allowedIds) !== []) {
                throw ValidationException::withMessages(['card_display_attribute_ids' => 'Only required or selected attributes for this Product Type can appear on the card.']);
            }
            $record->card_display_attribute_ids = $displayIds;
            $record->status = 'draft';
            $record->primary_category_id = null;
            $record->save();

            app(ProductAttributeValueManager::class)->persist($record);

            $record->categories()->sync($categoryIds);
            if ($primaryCategoryId !== null) {
                DB::table('products')->where('id', $record->getKey())->update(['primary_category_id' => $primaryCategoryId]);
            }

            $record->primary_category_id = $primaryCategoryId;
            $record->status = $targetStatus;
            $record->save();

            $record->load('primaryCategory');
            $newCanonical = app(ProductUrlResolver::class)->canonicalPath($record);
            app(ProductUrlResolver::class)->assertCanonicalAvailable($record, $newCanonical);
            if ($oldCanonical !== null && $oldCanonical !== $newCanonical) {
                app(ProductUrlResolver::class)->recordAlias($record, $oldCanonical);
            }

            return $record->load(['categories', 'primaryCategory']);
        });
    }
}
