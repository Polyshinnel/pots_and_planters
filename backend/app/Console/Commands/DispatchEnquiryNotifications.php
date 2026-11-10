<?php

namespace App\Console\Commands;

use App\Models\EnquiryDelivery;
use Illuminate\Console\Command;

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
            if (app(\App\Services\EnquiryNotificationDispatcher::class)->dispatch((int) $id)) {
                $queued++;
            }
        }

        $this->line("Queued {$queued} of {$deliveries->count()} due delivery jobs.");

        return self::SUCCESS;
    }
}
