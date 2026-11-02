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

    protected $fillable = ['name', 'code', 'type', 'unit', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function options(): HasMany
    {
        return $this->hasMany(AttributeOption::class)->orderBy('sort_order')->orderBy('id');
    }

    public function productTypes(): BelongsToMany
    {
        return $this->belongsToMany(ProductType::class, 'product_type_attribute')
            ->withPivot(['is_required', 'is_filterable', 'sort_order'])
            ->withTimestamps();
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
        });

        static::saving(function (Attribute $attribute): void {
            $attribute->name = is_string($attribute->name) ? trim($attribute->name) : $attribute->name;
            $attribute->code = self::normalizeKey($attribute->code);
            $attribute->unit = is_string($attribute->unit) ? trim($attribute->unit) : $attribute->unit;
            $attribute->unit = $attribute->unit === '' ? null : $attribute->unit;

            $validator = Validator::make($attribute->getAttributes(), [
                'name' => ['required', 'string', 'max:255'],
                'code' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', Rule::unique('attributes', 'code')->ignore($attribute->getKey())],
                'type' => ['required', 'in:'.implode(',', self::TYPES)],
                'unit' => ['nullable', 'string', 'max:32'],
                'is_active' => ['required', 'boolean'],
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            if ($attribute->type !== 'number' && $attribute->unit !== null) {
                throw ValidationException::withMessages(['unit' => 'A unit is only allowed for number attributes.']);
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
