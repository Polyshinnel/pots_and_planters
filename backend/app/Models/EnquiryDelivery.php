<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnquiryDelivery extends Model
{
    protected $fillable = [
        'outbox_event_id', 'recipient_id', 'chat_id_snapshot', 'recipient_name_snapshot', 'status',
        'attempts', 'last_attempt_at', 'next_attempt_at', 'sent_at', 'telegram_message_id', 'last_error_code', 'safe_error',
        'lease_token', 'lease_expires_at', 'dispatch_after_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer', 'last_attempt_at' => 'immutable_datetime',
            'next_attempt_at' => 'immutable_datetime', 'sent_at' => 'immutable_datetime', 'lease_expires_at' => 'immutable_datetime',
            'dispatch_after_at' => 'immutable_datetime',
        ];
    }

    public function outboxEvent(): BelongsTo
    {
        return $this->belongsTo(EnquiryOutboxEvent::class, 'outbox_event_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(TelegramRecipient::class, 'recipient_id')->withTrashed();
    }
}
