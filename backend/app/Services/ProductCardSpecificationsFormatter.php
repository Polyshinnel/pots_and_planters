<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\ProductType;

/** Formats the selected card attributes without converting decimal values to floats. */
class ProductCardSpecificationsFormatter
{
    /** @param array<string|int, mixed> $values @param list<int> $orderedAttributeIds */
    public function format(?int $productTypeId, array $values, array $orderedAttributeIds): string
    {
        if (! $productTypeId || $orderedAttributeIds === []) {
            return '';
        }

        $attributes = ProductType::query()->find($productTypeId)?->attributes()->with('options')->get()->keyBy('id') ?? collect();
        $parts = [];

        foreach ($orderedAttributeIds as $id) {
            $attribute = $attributes->get($id);
            if (! ($attribute instanceof Attribute)) {
                continue;
            }

            $value = $values[$id] ?? $values[(string) $id] ?? null;
            $formatted = $this->value($attribute, $value);
            if ($formatted === null) {
                continue;
            }

            $label = $attribute->short_label ?: $attribute->name;
            $parts[] = in_array($attribute->type, ['select', 'multiselect'], true)
                ? $formatted
                : trim($label.' '.$formatted);
        }

        return implode(' · ', $parts);
    }

    private function value(Attribute $attribute, mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if ($attribute->type === 'boolean') {
            $formatted = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No';
        } elseif (in_array($attribute->type, ['select', 'multiselect'], true)) {
            $ids = array_map('intval', $attribute->type === 'select' ? [$value] : (array) $value);
            $labels = $attribute->options->whereIn('id', $ids)->sortBy(fn ($option) => array_search((int) $option->id, $ids, true))->pluck('label')->all();
            if ($labels === []) {
                return null;
            }
            $formatted = implode(', ', $labels);
        } else {
            $formatted = (string) $value;
        }

        if ($attribute->type === 'number' && filled($attribute->unit)) {
            $formatted .= ' '.$attribute->unit;
        }

        return $formatted;
    }
}
