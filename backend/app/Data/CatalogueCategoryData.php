<?php

namespace App\Data;

use App\Models\Category;

final readonly class CatalogueCategoryData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
    ) {}

    public static function fromModel(Category $category): self
    {
        return new self($category->public_id, $category->name, $category->slug);
    }

    /** @return array{id: string, name: string, slug: string} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'slug' => $this->slug];
    }
}
