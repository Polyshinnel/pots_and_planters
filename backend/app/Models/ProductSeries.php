<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductSeries extends Model
{
    protected $fillable = ['brand_id', 'name', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    protected static function booted(): void
    {
        static::creating(function (ProductSeries $series): void {
            $series->public_id ??= (string) Str::uuid();
        });

        static::updating(function (ProductSeries $series): void {
            if ($series->isDirty('public_id')) {
                throw ValidationException::withMessages(['public_id' => 'The public identifier cannot be changed.']);
            }
        });

        static::saving(function (ProductSeries $series): void {
            $series->name = is_string($series->name) ? trim($series->name) : $series->name;
            $validator = Validator::make($series->getAttributes(), [
                'brand_id' => ['required', 'integer', 'exists:brands,id'],
                'name' => ['required', 'string', 'max:255'],
                'is_active' => ['required', 'boolean'],
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
        });
    }
}
