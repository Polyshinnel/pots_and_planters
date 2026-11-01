<?php

namespace App\Console\Commands;

use App\Jobs\DeliverEnquiryToTelegram;
use App\Models\EnquiryDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchEnquiryNotifications extends Command
{
    protected $signature = 'enquiries:dispatch-telegram {--limit=500}';

    protected $description = 'Dispatch due Telegram enquiry deliveries from the MariaDB outbox to Redis';

    public function handle(): int
    {
        $limit = max(1, min(2000, (int) $this->option('limit')));
        $deliveries = EnquiryDelivery::query()->where(function ($query): void {
            $query->where(fn ($pending) => $pending->where('status', 'pending')->where(fn ($due) => $due->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now())))
                ->orWhere(fn ($expired) => $expired->where('status', 'sending')->where(fn ($lease) => $lease->whereNull('lease_expires_at')->orWhere('lease_expires_at', '<=', now())));
        })->where(fn ($dispatch) => $dispatch->whereNull('dispatch_after_at')->orWhere('dispatch_after_at', '<=', now()))
            ->orderBy('id')->limit($limit)->pluck('id');

        $queued = 0;
        foreach ($deliveries as $id) {
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
                continue;
            }
            try {
                DeliverEnquiryToTelegram::dispatch((int) $id);
                $queued++;
            } catch (Throwable) {
                // MariaDB remains the source of truth; a later scheduler tick will retry this dispatch.
                EnquiryDelivery::query()->whereKey($id)->update(['dispatch_after_at' => null]);
                Log::warning('Telegram outbox dispatch failed; delivery remains available for recovery.', ['delivery_id' => (int) $id]);
                break;
            }
        }

        $this->line("Queued {$queued} of {$deliveries->count()} due delivery jobs.");

        return self::SUCCESS;
    }
}
