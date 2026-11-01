<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EnquiryOutboxEvent extends Model
{
    protected $fillable = ['enquiry_id', 'status', 'notifications_enabled_snapshot', 'available_at', 'processed_at'];

    protected function casts(): array
    {
        return ['notifications_enabled_snapshot' => 'boolean', 'available_at' => 'immutable_datetime', 'processed_at' => 'immutable_datetime'];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(EnquiryDelivery::class, 'outbox_event_id');
    }
}
