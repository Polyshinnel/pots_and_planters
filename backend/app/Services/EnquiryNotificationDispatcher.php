<?php

namespace App\Services;

use App\Jobs\DeliverEnquiryToTelegram;
use App\Models\EnquiryDelivery;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class EnquiryNotificationDispatcher
{
    public function dispatch(int $id): bool
    {
        $reserved = DB::transaction(function () use ($id): bool {
            $delivery = EnquiryDelivery::query()->whereKey($id)->lockForUpdate()->first();
            if (! $delivery || $delivery->dispatch_after_at?->isFuture()) {
                return false;
            }
            $eligible = ($delivery->status === 'pending' && (! $delivery->next_attempt_at || $delivery->next_attempt_at->isPast()))
                || ($delivery->status === 'sending' && (! $delivery->lease_expires_at || $delivery->lease_expires_at->isPast()));
            if (! $eligible) {
                return false;
            }
            $delivery->update(['dispatch_after_at' => now()->addSeconds(120)]);

            return true;
        });
        if (! $reserved) {
            return false;
        }
        try {
            Bus::dispatch(new DeliverEnquiryToTelegram($id));

            return true;
        } catch (Throwable) {
            // MariaDB remains the source of truth; a later scheduler tick will retry this dispatch.
            EnquiryDelivery::query()->whereKey($id)->update(['dispatch_after_at' => null]);
            Log::warning('Telegram outbox dispatch failed; delivery remains available for recovery.', ['delivery_id' => $id]);

            return false;
        }
    }
}
