<?php

namespace App\Services;

use App\Data\ProductListData;
use App\Exceptions\EnquiryIdempotencyConflict;
use App\Exceptions\EnquiryProductsUnavailable;
use App\Exceptions\EnquiryResourceUnavailable;
use App\Models\Brand;
use App\Models\Catalogue;
use App\Models\Category;
use App\Models\Enquiry;
use App\Models\EnquiryIdempotencyKey;
use App\Models\EnquiryOutboxEvent;
use App\Models\Product;
use App\Models\Supplier;
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

                $productSnapshots = in_array($payload['type'], ['product_enquiry', 'cart_enquiry'], true)
                    ? $this->resolveProductSnapshots($payload)
                    : [];

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
                    'technical_metadata' => ['apiVersion' => $payload['apiVersion'] ?? 1],
                    'submitted_at' => now(),
                ]);

                foreach ($productSnapshots as $position => $snapshot) {
                    $enquiry->items()->create([
                        'product_identifier' => $snapshot['product_public_id'],
                        'product_public_id' => $snapshot['product_public_id'],
                        'brand_public_id' => $snapshot['brand_public_id'],
                        'sku_snapshot' => $snapshot['sku'],
                        'brand_name_snapshot' => $snapshot['brand_name'],
                        'quantity' => $snapshot['quantity'],
                        'unit_price_aed' => $snapshot['unit_price_aed'],
                        'currency' => $snapshot['currency'],
                        'availability_snapshot' => true,
                        'snapshot_at' => $snapshot['snapshot_at'],
                        'product_snapshot' => $snapshot['product_snapshot'],
                        'position' => $position,
                    ]);
                }

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
                    $delivery = $event->deliveries()->create([
                        'recipient_id' => $recipient->id,
                        'chat_id_snapshot' => $recipient->chat_id,
                        'recipient_name_snapshot' => $recipient->name,
                        'status' => 'pending',
                    ]);

                    // Publish only after the enquiry and its durable outbox have committed.
                    DB::afterCommit(fn () => app(EnquiryNotificationDispatcher::class)->dispatch((int) $delivery->id));
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

    /** @return list<array<string, mixed>> */
    private function resolveProductSnapshots(array $payload): array
    {
        $inputItems = $payload['type'] === 'product_enquiry'
            ? [['productId' => $payload['product']['productId'], 'quantity' => $payload['product']['quantity']]]
            : $payload['items'];
        $ids = array_map(fn (array $item): string => strtolower($item['productId']), $inputItems);
        $sortedIds = $ids;
        sort($sortedIds, SORT_STRING);

        $products = Product::query()->whereIn('public_id', $sortedIds)->orderBy('public_id')
            ->lockForUpdate()->get()->keyBy(fn (Product $product): string => strtolower((string) $product->public_id));

        $productRows = $products->sortBy(fn (Product $product): string => strtolower((string) $product->public_id));
        $brandIds = $productRows->pluck('brand_id')->unique()->sort()->values()->all();
        $supplierIds = $productRows->pluck('supplier_id')->unique()->sort()->values()->all();
        $categoryIds = $productRows->pluck('primary_category_id')->filter()->unique()->sort()->values()->all();
        $brands = Brand::query()->whereIn('id', $brandIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $suppliers = Supplier::query()->whereIn('id', $supplierIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $categories = Category::query()->whereIn('id', $categoryIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        $unavailable = [];
        foreach ($ids as $id) {
            $product = $products->get($id);
            if (! $product || $product->status !== 'published'
                || ! $brands->has($product->brand_id)
                || ! $suppliers->has($product->supplier_id)
                || ! $categories->has($product->primary_category_id)
                || ! $suppliers->get($product->supplier_id)->is_active
                || $categories->get($product->primary_category_id)->status !== 'published') {
                $unavailable[] = $id;
            }
        }
        if ($unavailable !== []) {
            throw new EnquiryProductsUnavailable(array_values(array_unique($unavailable)));
        }

        $snapshotProducts = Product::query()->whereIn('id', $products->pluck('id'))
            ->with([
                'brand', 'primaryCategory', 'categories' => fn ($query) => $query->where('status', 'published'),
                'productType.attributes.options', 'attributeValues.selections.option', 'images.mediaAsset',
            ])->get()->keyBy(fn (Product $product): string => strtolower((string) $product->public_id));

        $result = [];
        foreach ($inputItems as $item) {
            /** @var Product $product */
            $product = $snapshotProducts->get(strtolower($item['productId']));
            $product->setRelation('brand', $brands->get($product->brand_id));
            $product->setRelation('primaryCategory', $categories->get($product->primary_category_id));
            $publicSnapshot = (new ProductListData($product))->toArray();
            $result[] = [
                'product_public_id' => (string) $product->public_id,
                'brand_public_id' => (string) $product->brand->public_id,
                'sku' => (string) $product->sku,
                'brand_name' => (string) $product->brand->name,
                'quantity' => (int) $item['quantity'],
                'unit_price_aed' => $product->price_aed === null ? null : (string) $product->price_aed,
                'currency' => 'AED',
                'snapshot_at' => now(),
                'product_snapshot' => $publicSnapshot,
            ];
        }

        return $result;
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
