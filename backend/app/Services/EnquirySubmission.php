<?php

namespace App\Services;

use App\Exceptions\EnquiryIdempotencyConflict;
use App\Exceptions\EnquiryResourceUnavailable;
use App\Models\Catalogue;
use App\Models\Enquiry;
use App\Models\EnquiryIdempotencyKey;
use App\Models\EnquiryOutboxEvent;
use App\Models\TelegramRecipient;
use App\Models\TelegramSetting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EnquirySubmission
{
    /** @return array{enquiry: Enquiry, notification_status: string, replayed: bool} */
    public function submit(array $payload, string $idempotencyKey, ?CatalogueDownloadGrantService $grantService = null): array
    {
        $grantService ??= app(CatalogueDownloadGrantService::class);
        $payload = Arr::except($payload, ['website']);
        $payloadHash = hash('sha256', json_encode($this->canonicalize($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $keyHash = hash('sha256', $idempotencyKey);

        try {
            return DB::transaction(function () use ($payload, $payloadHash, $keyHash, $grantService): array {
                $key = EnquiryIdempotencyKey::query()->where('key_hash', $keyHash)->lockForUpdate()->first();
                if ($key && $key->expires_at->isFuture()) {
                    if (! hash_equals($key->payload_hash, $payloadHash)) {
                        throw new EnquiryIdempotencyConflict;
                    }

                    $grant = $key->enquiry->type === 'catalogue_download' ? $grantService->issueFor($key->enquiry) : null;

                    return [
                        'enquiry' => $key->enquiry,
                        'notification_status' => $key->notification_status,
                        'replayed' => true,
                        'download_grant' => $grant,
                    ];
                }
                $key?->delete();

                $catalogue = null;
                if ($payload['type'] === 'catalogue_download') {
                    $catalogue = Catalogue::query()->where('public_id', $payload['catalogueId'])
                        ->where('status', 'published')->sharedLock()->first();
                    if (! $catalogue) {
                        throw new EnquiryResourceUnavailable;
                    }
                    if (! $grantService->safePath($catalogue->pdf_path) || ! Storage::disk('local')->exists($catalogue->pdf_path)) {
                        throw new EnquiryResourceUnavailable;
                    }
                }

                if (in_array($payload['type'], ['product_enquiry', 'cart_enquiry'], true)) {
                    // BE-08 has not introduced an authoritative product table yet. Never turn client labels/prices into snapshots.
                    throw new EnquiryResourceUnavailable;
                }

                $settings = TelegramSetting::query()->whereKey(1)->sharedLock()->first();
                $enabled = (bool) $settings?->notifications_enabled;

                $enquiry = Enquiry::query()->create([
                    'type' => $payload['type'],
                    'status' => 'new',
                    'name' => $payload['name'],
                    'phone' => $payload['phone'],
                    'email' => $payload['email'],
                    'company' => $payload['company'] ?? null,
                    'message' => $payload['message'] ?? null,
                    'role' => $payload['role'] ?? null,
                    'role_other' => ($payload['role'] ?? null) === 'other' ? ($payload['roleOther'] ?? null) : null,
                    'catalogue_id' => $catalogue?->id,
                    'catalogue_snapshot' => $catalogue ? ['publicId' => $catalogue->public_id, 'title' => $catalogue->title] : null,
                    'source_path' => Arr::get($payload, 'source.pagePath'),
                    'source_placement' => Arr::get($payload, 'source.placement'),
                    'technical_metadata' => ['apiVersion' => 1],
                    'submitted_at' => now(),
                ]);

                $recipients = $enabled
                    ? TelegramRecipient::query()->where('enabled', true)->lockForUpdate()->get(['id', 'name', 'chat_id'])
                    : collect();
                $notificationStatus = ! $enabled ? 'skipped_disabled' : ($recipients->isEmpty() ? 'pending_no_recipients' : 'pending');

                $event = EnquiryOutboxEvent::query()->create([
                    'enquiry_id' => $enquiry->id,
                    'status' => $notificationStatus,
                    'notifications_enabled_snapshot' => $enabled,
                    'available_at' => $notificationStatus === 'pending' ? now() : null,
                    'processed_at' => $notificationStatus === 'skipped_disabled' ? now() : null,
                ]);

                foreach ($recipients as $recipient) {
                    $event->deliveries()->create([
                        'recipient_id' => $recipient->id,
                        'chat_id_snapshot' => $recipient->chat_id,
                        'recipient_name_snapshot' => $recipient->name,
                        'status' => 'pending',
                    ]);
                }

                EnquiryIdempotencyKey::query()->create([
                    'key_hash' => $keyHash,
                    'payload_hash' => $payloadHash,
                    'enquiry_id' => $enquiry->id,
                    'notification_status' => $notificationStatus,
                    'expires_at' => now()->addDay(),
                ]);

                $grant = $catalogue ? $grantService->issueFor($enquiry) : null;

                return ['enquiry' => $enquiry, 'notification_status' => $notificationStatus, 'replayed' => false, 'download_grant' => $grant];
            }, attempts: 3);
        } catch (QueryException $exception) {
            // The unique key is the final arbiter when requests race before either can lock an absent row.
            $key = EnquiryIdempotencyKey::query()->where('key_hash', $keyHash)->first();
            if (! $key || $key->expires_at->isPast()) {
                throw $exception;
            }
            if (! hash_equals($key->payload_hash, $payloadHash)) {
                throw new EnquiryIdempotencyConflict;
            }

            $grant = $key->enquiry->type === 'catalogue_download'
                ? DB::transaction(fn () => $grantService->issueFor($key->enquiry))
                : null;

            return [
                'enquiry' => $key->enquiry,
                'notification_status' => $key->notification_status,
                'replayed' => true,
                'download_grant' => $grant,
            ];
        }
    }

    private function canonicalize(array $value): array
    {
        foreach ($value as &$item) {
            if (is_array($item) && ! array_is_list($item)) {
                $item = $this->canonicalize($item);
            } elseif (is_array($item)) {
                $item = array_map(fn ($nested) => is_array($nested) && ! array_is_list($nested) ? $this->canonicalize($nested) : $nested, $item);
            }
        }
        unset($item);
        ksort($value);

        return $value;
    }
}
