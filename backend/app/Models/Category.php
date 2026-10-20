<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    protected $fillable = ['public_id', 'slug', 'name', 'heading', 'description', 'tile_description', 'intro_heading', 'intro_paragraphs', 'image_path', 'image_alt', 'seo_title', 'seo_description', 'og_image_path', 'status', 'sort_order'];

    protected function casts(): array { return ['intro_paragraphs' => 'array', 'sort_order' => 'integer']; }

    public function getImageUrlAttribute(): ?string { return $this->image_path ? Storage::disk('public')->url($this->image_path) : null; }
    public function getOgImageUrlAttribute(): ?string { return $this->og_image_path ? Storage::disk('public')->url($this->og_image_path) : null; }
}
