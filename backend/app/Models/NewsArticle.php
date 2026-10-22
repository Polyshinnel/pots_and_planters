<?php

namespace App\Models;

use App\Services\NewsHtmlSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NewsArticle extends Model
{
    protected $fillable = [
        'category_id', 'title', 'slug', 'excerpt', 'body', 'cover_path', 'cover_alt',
        'og_image_path', 'published_at', 'status', 'seo_title', 'seo_description',
    ];

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return ['published_at' => 'date'];
    }

    protected static function booted(): void
    {
        static::creating(function (NewsArticle $article): void {
            $article->public_id ??= (string) Str::uuid();
        });

        static::updating(function (NewsArticle $article): void {
            if ($article->isDirty('public_id')) {
                throw ValidationException::withMessages(['public_id' => 'The public identifier cannot be changed.']);
            }
        });

        static::saving(function (NewsArticle $article): void {
            if (! in_array($article->status, ['draft', 'published', 'archived'], true)) {
                throw ValidationException::withMessages(['status' => 'The selected status is invalid.']);
            }

            if (blank($article->slug)) {
                $article->slug = static::generateUniqueSlug((string) $article->title);
            }

            if (! is_string($article->slug) || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $article->slug)) {
                throw ValidationException::withMessages(['slug' => 'The article slug must use lowercase kebab-case.']);
            }

            $slugIsTaken = static::query()->where('slug', $article->slug)
                ->when($article->exists, fn (Builder $query) => $query->whereKeyNot($article->getKey()))
                ->exists();
            if ($slugIsTaken) {
                throw ValidationException::withMessages(['slug' => 'The article slug has already been taken.']);
            }

            if ($article->body !== null) {
                $article->body = app(NewsHtmlSanitizer::class)->sanitize((string) $article->body);
            }

            if ($article->status === 'published') {
                $attributes = $article->getAttributes();
                $attributes['published_at'] = $article->published_at?->toDateString();
                $validator = Validator::make($attributes, [
                    'category_id' => ['required', 'integer', Rule::exists('news_categories', 'id')->where('is_active', true)],
                    'title' => ['required', 'string', 'max:255'],
                    'slug' => ['required', 'string', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
                    'excerpt' => ['required', 'string', 'min:1'],
                    'body' => ['required', 'string', 'min:1'],
                    'cover_path' => ['required', 'string', 'min:1'],
                    'cover_alt' => ['required', 'string', 'min:1', 'max:255'],
                    'published_at' => ['required', 'date_format:Y-m-d'],
                    'seo_title' => ['required', 'string', 'min:1', 'max:255'],
                    'seo_description' => ['required', 'string', 'min:1'],
                    'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
                ]);

                if ($validator->fails()) {
                    throw new ValidationException($validator);
                }
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'category_id');
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('title')->where('title', '<>', '')
            ->whereNotNull('excerpt')->where('excerpt', '<>', '')
            ->whereNotNull('body')->where('body', '<>', '')
            ->whereNotNull('cover_path')->where('cover_path', '<>', '')
            ->whereNotNull('cover_alt')->where('cover_alt', '<>', '')
            ->whereNotNull('published_at')
            ->whereNotNull('seo_title')->where('seo_title', '<>', '')
            ->whereNotNull('seo_description')->where('seo_description', '<>', '')
            ->whereHas('category', fn (Builder $category) => $category->where('is_active', true));
    }

    public function scopePublicOrder(Builder $query): Builder
    {
        return $query->orderByDesc('published_at')->orderBy('id');
    }

    public static function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'news';
        $slug = $base;
        $suffix = 2;

        while (static::query()->where('slug', $slug)->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
