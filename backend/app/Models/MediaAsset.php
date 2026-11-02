<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MediaAsset extends Model
{
    protected $fillable = ['disk', 'path', 'mime_type', 'size_bytes'];

    protected $hidden = ['disk', 'path', 'mime_type', 'size_bytes', 'processing_token'];

    protected function casts(): array
    {
        return [
            'variants' => 'array',
            'queued_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'processing_finished_at' => 'datetime',
        ];
    }

    public function canRetryProcessing(): bool
    {
        $cutoff = now()->subSeconds(config('media.stale_after_seconds'));

        return $this->processing_status === 'failed'
            || ($this->processing_status === 'pending' && ($this->queued_at === null || $this->queued_at->lte($cutoff)))
            || ($this->processing_status === 'processing' && $this->processing_started_at?->lte($cutoff));
    }

    public function variantUrl(string $name = 'thumb'): ?string
    {
        if ($this->processing_status !== 'ready' || ! isset($this->variants[$name]['path'])) {
            return null;
        }
        $path = $this->variants[$name]['path'];

        return Storage::disk('public')->exists($path) ? Storage::disk('public')->url($path) : null;
    }

    protected static function booted(): void
    {
        static::creating(fn (MediaAsset $asset) => $asset->public_id ??= (string) Str::uuid());

        static::updating(function (MediaAsset $asset): void {
            if ($asset->isDirty(['public_id', 'disk', 'path'])) {
                throw ValidationException::withMessages(['media_asset' => 'A media asset identifier and storage location cannot be changed.']);
            }
        });
    }

    public function productImages(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }
}
