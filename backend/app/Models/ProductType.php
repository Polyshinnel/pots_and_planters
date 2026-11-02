<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductType extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'product_type_attribute')
            ->withPivot(['is_required', 'is_filterable', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderBy('attributes.name')
            ->orderBy('attributes.id');
    }

    protected static function booted(): void
    {
        static::creating(function (ProductType $productType): void {
            $productType->public_id ??= (string) Str::uuid();
        });

        static::updating(function (ProductType $productType): void {
            if ($productType->isDirty('public_id')) {
                throw ValidationException::withMessages(['public_id' => 'The public identifier cannot be changed.']);
            }
        });

        static::saving(function (ProductType $productType): void {
            $productType->name = is_string($productType->name) ? trim($productType->name) : $productType->name;
            $validator = Validator::make($productType->getAttributes(), [
                'name' => ['required', 'string', 'max:255'],
                'is_active' => ['required', 'boolean'],
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
        });
    }
}
