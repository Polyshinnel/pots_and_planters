<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Brand extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function productSeries(): HasMany
    {
        return $this->hasMany(ProductSeries::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Brand $brand): void {
            $brand->public_id ??= (string) Str::uuid();
        });

        static::updating(function (Brand $brand): void {
            if ($brand->isDirty('public_id')) {
                throw ValidationException::withMessages(['public_id' => 'The public identifier cannot be changed.']);
            }
        });

        static::saving(function (Brand $brand): void {
            $brand->name = is_string($brand->name) ? trim($brand->name) : $brand->name;
            $validator = Validator::make($brand->getAttributes(), [
                'name' => ['required', 'string', 'max:255'],
                'is_active' => ['required', 'boolean'],
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
        });
    }
}
