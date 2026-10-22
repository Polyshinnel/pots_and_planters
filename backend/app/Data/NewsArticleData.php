<?php

namespace App\Data;

use App\Models\NewsArticle;
use App\Services\NewsHtmlSanitizer;
use Illuminate\Support\Facades\Storage;

final readonly class NewsArticleData
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $title,
        public string $excerpt,
        public NewsCategoryData $category,
        public ?string $coverImage,
        public string $coverAlt,
        public string $publishedAt,
        public string $seoTitle,
        public string $seoDescription,
        public ?string $body = null,
        public ?string $ogImage = null,
    ) {}

    public static function fromModel(NewsArticle $article, bool $includeBody = false): self
    {
        return new self(
            id: $article->public_id,
            slug: $article->slug,
            title: $article->title,
            excerpt: $article->excerpt,
            category: NewsCategoryData::fromModel($article->category),
            coverImage: $article->cover_path ? Storage::disk('public')->url($article->cover_path) : null,
            coverAlt: $article->cover_alt,
            publishedAt: $article->published_at->toDateString(),
            seoTitle: $article->seo_title,
            seoDescription: $article->seo_description,
            body: $includeBody ? app(NewsHtmlSanitizer::class)->sanitize($article->body) : null,
            ogImage: $includeBody && $article->og_image_path ? Storage::disk('public')->url($article->og_image_path) : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(bool $includeBody = false): array
    {
        $data = [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'category' => $this->category->toArray(),
            'coverImage' => $this->coverImage,
            'coverAlt' => $this->coverAlt,
            'publishedAt' => $this->publishedAt,
            'seoTitle' => $this->seoTitle,
            'seoDescription' => $this->seoDescription,
        ];

        if ($includeBody) {
            $data['body'] = $this->body;
            $data['ogImage'] = $this->ogImage;
        }

        return $data;
    }
}
