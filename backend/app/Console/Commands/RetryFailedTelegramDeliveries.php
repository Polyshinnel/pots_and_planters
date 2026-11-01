<?php

namespace App\Console\Commands;

use App\Models\EnquiryDelivery;
use App\Models\EnquiryIdempotencyKey;
use App\Models\EnquiryOutboxEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RetryFailedTelegramDeliveries extends Command
{
    protected $signature = 'enquiries:retry-failed-telegram {--event= : Limit reset to one outbox event ID}';

    protected $description = 'Return terminal Telegram deliveries to the durable outbox for retry';

    public function handle(): int
    {
        $query = EnquiryDelivery::query()->where('status', 'failed');
        if ($this->option('event') !== null) {
            $query->where('outbox_event_id', (int) $this->option('event'));
        }

        $count = DB::transaction(function () use ($query): int {
            $ids = $query->lockForUpdate()->pluck('id');
            EnquiryDelivery::query()->whereIn('id', $ids)->update([
                'status' => 'pending', 'attempts' => 0, 'last_error_code' => null, 'safe_error' => null,
                'next_attempt_at' => null, 'lease_token' => null, 'lease_expires_at' => null, 'updated_at' => now(),
                'dispatch_after_at' => null,
            ]);
            if ($ids->isNotEmpty()) {
                $events = EnquiryOutboxEvent::query()->whereIn('id', EnquiryDelivery::query()->whereIn('id', $ids)->pluck('outbox_event_id')->unique())->get(['id', 'enquiry_id']);
                EnquiryOutboxEvent::query()->whereIn('id', $events->pluck('id'))
                    ->update(['status' => 'pending', 'processed_at' => null, 'updated_at' => now()]);
                EnquiryIdempotencyKey::query()->whereIn('enquiry_id', $events->pluck('enquiry_id'))
                    ->update(['notification_status' => 'pending', 'updated_at' => now()]);
            }

            return $ids->count();
        });

        $this->info("Reset {$count} failed delivery(s).");

        return self::SUCCESS;
    }
}
