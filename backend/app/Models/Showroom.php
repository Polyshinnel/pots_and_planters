<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class Showroom extends Model
{
    protected $fillable = [
        'public_id', 'name', 'description', 'address', 'city', 'country', 'phones', 'emails',
        'instagram_handle', 'instagram_url', 'status', 'sort_order',
    ];

    protected $attributes = ['status' => 'draft', 'sort_order' => 0];

    protected function casts(): array
    {
        return ['phones' => 'array', 'emails' => 'array', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (Showroom $showroom): void {
            if (! in_array($showroom->status, ['draft', 'published'], true)) {
                throw ValidationException::withMessages(['status' => 'The selected status is invalid.']);
            }
            if ($showroom->status !== 'published') {
                return;
            }

            $attributes = $showroom->getAttributes();
            $attributes['phones'] = $showroom->phones;
            $attributes['emails'] = $showroom->emails;
            $validator = Validator::make($attributes, [
                'public_id' => ['required', 'string', 'max:255'],
                'name' => ['required', 'string', 'min:1', 'max:255'],
                'address' => ['required', 'string', 'min:1'],
                'city' => ['required', 'string', 'min:1', 'max:255'],
                'country' => ['required', 'string', 'min:1', 'max:255'],
                'description' => ['nullable', 'string'],
                'phones' => ['nullable', 'array'],
                'phones.*.label' => ['required', 'string', 'max:255'],
                'phones.*.href' => ['required', 'string', 'max:2048', 'regex:/^tel:\+?[0-9(). -]+$/'],
                'emails' => ['nullable', 'array'],
                'emails.*' => ['required', 'email', 'max:255'],
                'instagram_handle' => ['nullable', 'required_with:instagram_url', 'string', 'max:255'],
                'instagram_url' => ['nullable', 'required_with:instagram_handle', 'url:http,https', 'max:2048'],
                'sort_order' => ['required', 'integer', 'min:0'],
            ]);
            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopePublicOrder(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
