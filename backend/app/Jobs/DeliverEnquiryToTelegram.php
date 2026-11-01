<?php

namespace App\Jobs;

use App\Models\Enquiry;
use App\Models\EnquiryDelivery;
use App\Models\EnquiryIdempotencyKey;
use App\Models\EnquiryOutboxEvent;
use App\Models\TelegramSetting;
use App\Services\TelegramBotClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class DeliverEnquiryToTelegram implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 25;

    private const MAX_ATTEMPTS = 8;

    public function __construct(public readonly int $deliveryId)
    {
        $this->onConnection('redis')->onQueue('default');
    }

    public function handle(TelegramBotClient $client): void
    {
        $claim = $this->claim();
        if (! $claim) {
            return;
        }

        [$lease, $delivery] = $claim;
        $settings = TelegramSetting::query()->find(1);
        if (! $settings?->notifications_enabled || blank($settings?->bot_token)) {
            $this->finish($lease, $settings?->notifications_enabled ? 'skipped_missing_token' : 'skipped_disabled', null, null, null);

            return;
        }

        $enquiry = Enquiry::query()->with('outboxEvent')->find($delivery->outboxEvent->enquiry_id);
        if (! $enquiry) {
            $this->finish($lease, 'failed', 'enquiry_missing', 'Enquiry record unavailable', null);

            return;
        }

        try {
            $result = $client->sendMessage($settings, (string) $delivery->chat_id_snapshot, $this->message($enquiry));
        } catch (Throwable) {
            $result = ['ok' => false, 'message_id' => null, 'error' => 'Telegram network failure', 'retry_after' => null, 'permanent' => false];
        }

        if ($result['ok']) {
            $this->finish($lease, 'sent', null, null, $result['message_id']);

            return;
        }

        $this->failedAttempt($lease, $result['error'], $result['retry_after'], $result['permanent']);
    }

    private function claim(): ?array
    {
        return DB::transaction(function (): ?array {
            $delivery = EnquiryDelivery::query()->whereKey($this->deliveryId)->lockForUpdate()->first();
            if (! $delivery || $delivery->status === 'sent' || $delivery->status === 'failed') {
                return null;
            }
            if ($delivery->status === 'sending' && $delivery->lease_expires_at?->isFuture()) {
                return null;
            }
            if ($delivery->status === 'pending' && $delivery->next_attempt_at?->isFuture()) {
                return null;
            }
            if ($delivery->attempts >= self::MAX_ATTEMPTS) {
                $delivery->update(['status' => 'failed', 'safe_error' => 'Retry limit reached', 'last_error_code' => 'retry_limit', 'lease_token' => null, 'lease_expires_at' => null]);
                $this->refreshEvent($delivery->outbox_event_id);

                return null;
            }

            $lease = (string) Str::uuid();
            $delivery->update([
                'status' => 'sending', 'lease_token' => $lease, 'lease_expires_at' => now()->addSeconds(60),
                'dispatch_after_at' => null, 'attempts' => $delivery->attempts + 1, 'last_attempt_at' => now(), 'next_attempt_at' => null,
            ]);

            return [$lease, $delivery->fresh(['outboxEvent'])];
        });
    }

    private function finish(string $lease, string $status, ?string $code, ?string $error, ?string $messageId): void
    {
        DB::transaction(function () use ($lease, $status, $code, $error, $messageId): void {
            $delivery = EnquiryDelivery::query()->whereKey($this->deliveryId)->lockForUpdate()->first();
            if (! $delivery || $delivery->lease_token !== $lease) {
                return;
            }
            $delivery->update([
                'status' => $status, 'last_error_code' => $code, 'safe_error' => $error, 'telegram_message_id' => $messageId,
                'sent_at' => $status === 'sent' ? now() : null, 'lease_token' => null, 'lease_expires_at' => null,
            ]);
            $this->refreshEvent($delivery->outbox_event_id);
        });
    }

    private function failedAttempt(string $lease, string $error, ?int $retryAfter, bool $permanent): void
    {
        DB::transaction(function () use ($lease, $error, $retryAfter, $permanent): void {
            $delivery = EnquiryDelivery::query()->whereKey($this->deliveryId)->lockForUpdate()->first();
            if (! $delivery || $delivery->lease_token !== $lease) {
                return;
            }

            $terminal = $permanent || $delivery->attempts >= self::MAX_ATTEMPTS;
            $delay = $retryAfter ?? min(21600, (2 ** min(12, $delivery->attempts)) + random_int(0, 15));
            $delivery->update([
                'status' => $terminal ? 'failed' : 'pending', 'last_error_code' => $permanent ? 'telegram_rejected' : ($retryAfter ? 'telegram_rate_limited' : 'telegram_temporary'),
                'safe_error' => $error, 'next_attempt_at' => $terminal ? null : now()->addSeconds(min(21600, $delay)),
                'lease_token' => null, 'lease_expires_at' => null,
            ]);
            $this->refreshEvent($delivery->outbox_event_id);
        });
    }

    private function refreshEvent(int $eventId): void
    {
        $event = EnquiryOutboxEvent::query()->find($eventId);
        if (! $event) {
            return;
        }
        $states = $event->deliveries()->pluck('status');
        $status = $states->contains('pending') || $states->contains('sending') ? 'pending'
            : ($states->contains('failed') ? 'failed' : ($states->every(fn (string $state): bool => $state === 'sent') ? 'sent' : 'skipped'));
        $event->update(['status' => $status, 'processed_at' => in_array($status, ['pending'], true) ? null : now()]);
        EnquiryIdempotencyKey::query()->where('enquiry_id', $event->enquiry_id)->update(['notification_status' => $status]);
    }

    private function message(Enquiry $enquiry): string
    {
        $type = match ($enquiry->type) {
            'contact' => 'Contact enquiry',
            'catalogue_download' => 'Catalogue download',
            default => (string) $enquiry->type,
        };
        $lines = [
            '🔔 <b>New '.e($type).'</b>',
            '👤 <b>Name:</b> '.e((string) $enquiry->name),
            '📞 <b>Phone:</b> '.e((string) $enquiry->phone),
            '✉️ <b>Email:</b> '.e((string) $enquiry->email),
        ];
        foreach (['company' => ['🏢', 'Company'], 'role' => ['💼', 'Role'], 'message' => ['💬', 'Message']] as $field => [$emoji, $label]) {
            if (filled($enquiry->{$field})) {
                $value = str_replace(["\r", "\0"], '', (string) $enquiry->{$field});
                $lines[] = $emoji.' <b>'.$label.':</b> '.e($value);
            }
        }
        if ($enquiry->catalogue_snapshot) {
            $lines[] = '📚 <b>Catalogue:</b> '.e((string) ($enquiry->catalogue_snapshot['title'] ?? ''));
        }

        return implode("\n", $lines);
    }
}
