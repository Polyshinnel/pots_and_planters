<?php

namespace App\Data;

use App\Models\NewsCategory;

final readonly class NewsCategoryData
{
    public function __construct(public string $name, public string $slug) {}

    public static function fromModel(NewsCategory $category): self
    {
        return new self($category->name, $category->slug);
    }

    /** @return array{name: string, slug: string} */
    public function toArray(): array
    {
        return ['name' => $this->name, 'slug' => $this->slug];
    }
}
