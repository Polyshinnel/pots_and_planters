<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Supplier extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (Supplier $supplier): void {
            $supplier->public_id ??= (string) Str::uuid();
        });

        static::updating(function (Supplier $supplier): void {
            if ($supplier->isDirty('public_id')) {
                throw ValidationException::withMessages(['public_id' => 'The public identifier cannot be changed.']);
            }
        });

        static::saving(function (Supplier $supplier): void {
            $supplier->name = is_string($supplier->name) ? trim($supplier->name) : $supplier->name;
            $validator = Validator::make($supplier->getAttributes(), [
                'name' => ['required', 'string', 'max:255'],
                'is_active' => ['required', 'boolean'],
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
        });
    }
}
