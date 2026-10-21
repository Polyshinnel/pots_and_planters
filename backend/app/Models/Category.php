<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class Category extends Model
{
    protected $fillable = ['public_id', 'slug', 'name', 'heading', 'description', 'tile_description', 'intro_heading', 'intro_paragraphs', 'image_path', 'image_alt', 'seo_title', 'seo_description', 'og_image_path', 'status', 'sort_order'];

    protected function casts(): array
    {
        return ['intro_paragraphs' => 'array', 'sort_order' => 'integer'];
    }

    public function delete(): ?bool
    {
        return DB::transaction(function () {
            static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            $usedByPublishedCatalogue = $this->catalogues()
                ->where('catalogues.status', 'published')
                ->lockForUpdate()
                ->get()
                ->contains(fn (Catalogue $catalogue): bool => $catalogue->categories()->count() === 1);

            if ($usedByPublishedCatalogue) {
                throw ValidationException::withMessages(['category' => 'This category is the only category assigned to a published catalogue.']);
            }

            return parent::delete();
        });
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function getOgImageUrlAttribute(): ?string
    {
        return $this->og_image_path ? Storage::disk('public')->url($this->og_image_path) : null;
    }

    public function catalogues(): BelongsToMany
    {
        return $this->belongsToMany(Catalogue::class, 'catalogue_category');
    }
}
