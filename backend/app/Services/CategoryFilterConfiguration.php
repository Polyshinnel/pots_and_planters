<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryFilterConfiguration
{
    public const DISPLAY_TYPES = [
        'text' => ['values' => 'List of text values'],
        'number' => ['range' => 'Numeric range (minimum–maximum)'],
        'boolean' => ['boolean' => 'Yes / No'],
        'select' => ['options' => 'Single-choice options'],
        'multiselect' => ['multi_options' => 'Multiple-choice options'],
    ];

    public function attach(Category $category, int $attributeId, string $displayType, int $sortOrder): void
    {
        DB::transaction(function () use ($category, $attributeId, $displayType, $sortOrder): void {
            $attribute = Attribute::query()->whereKey($attributeId)->lockForUpdate()->first();
            if (! $attribute || ! $attribute->is_active || ! $attribute->is_filterable || ! $attribute->productTypes()->exists()) {
                throw ValidationException::withMessages(['recordId' => 'Choose an active, filterable Attribute assigned to at least one Product Type.']);
            }

            $this->validatePresentation($attribute, $displayType, $sortOrder);
            if ($category->filterAttributes()->whereKey($attributeId)->exists()) {
                throw ValidationException::withMessages(['recordId' => 'This Attribute is already configured for the category.']);
            }

            try {
                $category->filterAttributes()->attach($attributeId, ['display_type' => $displayType, 'sort_order' => $sortOrder]);
            } catch (QueryException $exception) {
                if (! str_contains(strtolower($exception->getMessage()), 'duplicate')) {
                    throw $exception;
                }
                throw ValidationException::withMessages(['recordId' => 'This Attribute is already configured for the category. Refresh and try again.']);
            }
        });
    }

    public function update(Category $category, int $attributeId, string $displayType, int $sortOrder): void
    {
        DB::transaction(function () use ($category, $attributeId, $displayType, $sortOrder): void {
            $attribute = Attribute::query()->whereKey($attributeId)->lockForUpdate()->firstOrFail();
            $this->validatePresentation($attribute, $displayType, $sortOrder);

            if (! $category->filterAttributes()->whereKey($attributeId)->exists()) {
                throw ValidationException::withMessages(['recordId' => 'This category filter no longer exists. Refresh and try again.']);
            }

            $category->filterAttributes()->updateExistingPivot($attributeId, ['display_type' => $displayType, 'sort_order' => $sortOrder]);
        });
    }

    public function detach(Category $category, int $attributeId): void
    {
        DB::transaction(fn () => $category->filterAttributes()->detach($attributeId));
    }

    public function reorder(Category $category, array $orders): void
    {
        DB::transaction(function () use ($category, $orders): void {
            $existingIds = $category->filterAttributes()->lockForUpdate()->pluck('attributes.id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
            $submittedIds = collect(array_keys($orders))->map(fn ($id): int => (int) str_replace('order_', '', (string) $id))->sort()->values()->all();

            if ($existingIds !== $submittedIds) {
                throw ValidationException::withMessages(['filters' => 'The filter list changed. Refresh the page and try again.']);
            }

            foreach ($orders as $sortOrder) {
                $sortOrder = filter_var($sortOrder, FILTER_VALIDATE_INT);
                if ($sortOrder === false || $sortOrder < 0) {
                    throw ValidationException::withMessages(['filters' => 'Every filter order must be zero or greater.']);
                }
            }

            foreach ($orders as $key => $sortOrder) {
                $attributeId = (int) str_replace('order_', '', (string) $key);
                $category->filterAttributes()->updateExistingPivot($attributeId, ['sort_order' => (int) $sortOrder]);
            }
        });
    }

    private function validatePresentation(Attribute $attribute, string $displayType, int $sortOrder): void
    {
        if ($sortOrder < 0) {
            throw ValidationException::withMessages(['sort_order' => 'Order must be zero or greater.']);
        }

        if (! isset(self::DISPLAY_TYPES[$attribute->type][$displayType])) {
            throw ValidationException::withMessages(['display_type' => 'Choose a presentation compatible with this Attribute type.']);
        }
    }
}
