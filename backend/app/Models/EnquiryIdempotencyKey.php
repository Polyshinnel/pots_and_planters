<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnquiryIdempotencyKey extends Model
{
    protected $fillable = ['key_hash', 'payload_hash', 'enquiry_id', 'notification_status', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime'];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }
}
