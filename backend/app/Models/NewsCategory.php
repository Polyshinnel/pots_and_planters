<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NewsCategory extends Model
{
    protected $fillable = ['name', 'slug', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (NewsCategory $category): void {
            if (blank($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });

        static::saving(function (NewsCategory $category): void {
            if (! is_string($category->name) || trim($category->name) === '') {
                throw ValidationException::withMessages(['name' => 'A category name is required.']);
            }

            if (! is_string($category->slug) || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $category->slug)) {
                throw ValidationException::withMessages(['slug' => 'The category slug must use lowercase kebab-case.']);
            }

            if (static::query()->where('slug', $category->slug)->when($category->exists, fn (Builder $query) => $query->whereKeyNot($category->getKey()))->exists()) {
                throw ValidationException::withMessages(['slug' => 'The category slug has already been taken.']);
            }
        });
    }

    public function articles(): HasMany
    {
        return $this->hasMany(NewsArticle::class, 'category_id');
    }
}
