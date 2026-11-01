<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Enquiry extends Model
{
    protected $fillable = [
        'type', 'status', 'name', 'phone', 'email', 'company', 'message', 'role', 'role_other',
        'catalogue_id', 'catalogue_snapshot', 'source_path', 'source_placement', 'technical_metadata', 'submitted_at',
    ];

    protected function casts(): array
    {
        return ['catalogue_snapshot' => 'array', 'technical_metadata' => 'array', 'submitted_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (Enquiry $enquiry) => $enquiry->public_id ??= (string) Str::uuid());
    }

    public function items(): HasMany
    {
        return $this->hasMany(EnquiryItem::class);
    }

    public function outboxEvent(): HasOne
    {
        return $this->hasOne(EnquiryOutboxEvent::class);
    }

    public function catalogueDownloadGrants(): HasMany
    {
        return $this->hasMany(CatalogueDownloadGrant::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(EnquiryActivity::class)->latest('created_at');
    }
}
