<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AttributeOption extends Model
{
    protected $fillable = ['attribute_id', 'label', 'value', 'sort_order', 'is_active'];

    protected $attributes = ['sort_order' => 0, 'is_active' => true];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    protected static function booted(): void
    {
        static::creating(function (AttributeOption $option): void {
            $option->public_id ??= (string) Str::uuid();
        });

        static::updating(function (AttributeOption $option): void {
            if ($option->isDirty('public_id')) {
                throw ValidationException::withMessages(['public_id' => 'The public identifier cannot be changed.']);
            }
        });

        static::saving(function (AttributeOption $option): void {
            $option->label = is_string($option->label) ? trim($option->label) : $option->label;
            $option->value = Attribute::normalizeKey($option->value);

            $validator = Validator::make($option->getAttributes(), [
                'attribute_id' => ['required', 'integer', 'exists:attributes,id'],
                'label' => ['required', 'string', 'max:255'],
                'value' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', Rule::unique('attribute_options', 'value')->where('attribute_id', $option->attribute_id)->ignore($option->getKey())],
                'sort_order' => ['required', 'integer'],
                'is_active' => ['required', 'boolean'],
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $parent = Attribute::query()->find($option->attribute_id);
            if (! $parent || ! in_array($parent->type, ['select', 'multiselect'], true)) {
                throw ValidationException::withMessages(['attribute_id' => 'Options are only allowed for select and multiselect attributes.']);
            }
        });
    }
}
