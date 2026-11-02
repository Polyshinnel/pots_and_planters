<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Attribute extends Model
{
    public const TYPES = ['text', 'number', 'boolean', 'select', 'multiselect'];

    protected $fillable = ['name', 'code', 'type', 'unit', 'short_label', 'is_active', 'is_filterable'];

    protected $attributes = ['unit' => 'cm', 'is_active' => true, 'is_filterable' => false];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_filterable' => 'boolean'];
    }

    public function options(): HasMany
    {
        return $this->hasMany(AttributeOption::class)->orderBy('sort_order')->orderBy('id');
    }

    public function productTypes(): BelongsToMany
    {
        return $this->belongsToMany(ProductType::class, 'product_type_attribute')
            ->withPivot(['is_required', 'sort_order'])
            ->withTimestamps();
    }

    public function filterCategories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_attribute_filters')
            ->withPivot(['display_type', 'sort_order'])
            ->withTimestamps();
    }

    public function selectedProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_attribute_assignments')->withTimestamps();
    }

    protected static function booted(): void
    {
        static::creating(function (Attribute $attribute): void {
            $attribute->public_id ??= (string) Str::uuid();
        });

        static::updating(function (Attribute $attribute): void {
            if ($attribute->isDirty('public_id')) {
                throw ValidationException::withMessages(['public_id' => 'The public identifier cannot be changed.']);
            }

            if ($attribute->isDirty('type') && $attribute->options()->exists()) {
                throw ValidationException::withMessages(['type' => 'The type cannot be changed while options exist. Remove or migrate the options first.']);
            }

            if ($attribute->isDirty('type') && ProductAttributeValue::query()->where('attribute_id', $attribute->getKey())->exists()) {
                throw ValidationException::withMessages(['type' => 'The type cannot be changed while products have saved values for this attribute. Clear or migrate those values first.']);
            }

            if ($attribute->isDirty('type') && $attribute->filterCategories()->exists()) {
                throw ValidationException::withMessages(['type' => 'The type cannot be changed while this attribute is configured as a category filter. Remove its category filter assignments first.']);
            }
        });

        static::saving(function (Attribute $attribute): void {
            $attribute->name = is_string($attribute->name) ? trim($attribute->name) : $attribute->name;
            $attribute->code = self::normalizeKey($attribute->code);
            $attribute->unit = is_string($attribute->unit) ? trim($attribute->unit) : '';
            $attribute->unit = $attribute->unit === '' ? 'cm' : $attribute->unit;
            $attribute->short_label = is_string($attribute->short_label) ? trim(strip_tags($attribute->short_label)) : $attribute->short_label;
            $attribute->short_label = $attribute->short_label === '' ? null : $attribute->short_label;

            $validator = Validator::make($attribute->getAttributes(), [
                'name' => ['required', 'string', 'max:255'],
                'code' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', Rule::unique('attributes', 'code')->ignore($attribute->getKey())],
                'type' => ['required', 'in:'.implode(',', self::TYPES)],
                'unit' => ['required', 'string', 'max:32'],
                'short_label' => ['nullable', 'string', 'max:32'],
                'is_active' => ['required', 'boolean'],
                'is_filterable' => ['required', 'boolean'],
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

        });
    }

    public static function normalizeKey(mixed $key): mixed
    {
        if (! is_string($key)) {
            return $key;
        }

        return Str::of(trim($key))->lower()->replaceMatches('/[\s-]+/', '_')->toString();
    }
}
