<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'media_asset_id', 'sort_order', 'alt_override', 'is_main'];

    protected function casts(): array
    {
        return ['is_main' => 'boolean', 'sort_order' => 'integer'];
    }

    public function setAltOverrideAttribute(?string $value): void
    {
        $this->attributes['alt_override'] = filled($value) ? trim($value) : null;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    public function getEffectiveAltAttribute(): string
    {
        if (filled($this->alt_override)) {
            return $this->alt_override;
        }

        $orderedIds = $this->product?->images()->orderBy('sort_order')->orderBy('id')->pluck('id')->all() ?? [];
        $position = array_search($this->getKey(), $orderedIds, true);
        $alt = 'Product image of '.($this->product?->name ?? 'product');

        return $position !== false && $position > 0 ? $alt.' '.($position + 1) : $alt;
    }
}
