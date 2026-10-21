<?php

namespace App\Data;

use App\Models\Catalogue;
use Illuminate\Support\Facades\Storage;

final readonly class CatalogueData
{
    /** @param list<CatalogueCategoryData> $categories */
    public function __construct(
        public string $id,
        public string $slug,
        public string $title,
        public ?string $description,
        public ?string $coverImage,
        public ?string $coverAlt,
        public array $categoryIds,
        public array $categories,
    ) {}

    public static function fromModel(Catalogue $catalogue): self
    {
        $categories = $catalogue->categories
            ->where('status', 'published')
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->map(fn ($category): CatalogueCategoryData => CatalogueCategoryData::fromModel($category))
            ->values()
            ->all();

        return new self(
            id: $catalogue->public_id,
            slug: $catalogue->slug,
            title: $catalogue->title,
            description: $catalogue->description,
            coverImage: $catalogue->cover_path ? Storage::disk('public')->url($catalogue->cover_path) : null,
            coverAlt: $catalogue->cover_alt,
            categoryIds: array_map(fn (CatalogueCategoryData $category): string => $category->id, $categories),
            categories: $categories,
        );
    }

    /** @return array{id: string, slug: string, title: string, description: ?string, coverImage: ?string, coverAlt: ?string, categoryIds: list<string>, categories: list<array{id: string, name: string, slug: string}>} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'coverImage' => $this->coverImage,
            'coverAlt' => $this->coverAlt,
            'categoryIds' => $this->categoryIds,
            'categories' => array_map(fn (CatalogueCategoryData $category): array => $category->toArray(), $this->categories),
        ];
    }
}
