<?php

namespace Tests\Feature;

use App\Jobs\DeliverEnquiryToTelegram;
use App\Models\CatalogueDownloadGrant;
use App\Models\Enquiry;
use App\Models\EnquiryDelivery;
use App\Models\EnquiryOutboxEvent;
use App\Models\TelegramSetting;
use App\Services\TelegramBotClient;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnquiryDeliveryAndDownloadsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Http::preventStrayRequests();
        Cache::flush();
    }

    private function settings(array $overrides = []): TelegramSetting
    {
        return TelegramSetting::query()->forceCreate(array_merge([
            'id' => 1, 'notifications_enabled' => true, 'bot_token' => '123456:fake-token',
            'proxy_enabled' => false,
        ], $overrides));
    }

    private function enquiry(array $overrides = []): Enquiry
    {
        return Enquiry::query()->create(array_merge([
            'type' => 'contact', 'status' => 'new', 'name' => 'Taylor Example', 'phone' => '+971500000000',
            'email' => 'taylor@example.com', 'message' => 'Please call.', 'submitted_at' => now(),
        ], $overrides));
    }

    private function delivery(Enquiry $enquiry, string $chatId = '-1001234567890'): EnquiryDelivery
    {
        $event = EnquiryOutboxEvent::query()->create([
            'enquiry_id' => $enquiry->id, 'status' => 'pending', 'notifications_enabled_snapshot' => true, 'available_at' => now(),
        ]);

        return $event->deliveries()->create(['chat_id_snapshot' => $chatId, 'recipient_name_snapshot' => 'Test group', 'status' => 'pending']);
    }

    private function submitCatalogue(): array
    {
        $catalogueId = (string) Str::uuid();
        Storage::disk('local')->put('catalogues/guide.pdf', '%PDF-1.4 private guide');
        DB::table('catalogues')->insert([
            'public_id' => $catalogueId, 'slug' => 'guide', 'title' => 'Guide', 'pdf_path' => 'catalogues/guide.pdf',
            'cover_path' => 'guide.webp', 'status' => 'published', 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $payload = ['type' => 'catalogue_download', 'name' => 'Taylor Example', 'phone' => '+971500000000', 'email' => 'taylor@example.com', 'catalogueId' => $catalogueId, 'role' => 'architect'];
        $response = $this->withHeader('Idempotency-Key', 'catalogue-request-00001')->postJson('/api/v1/enquiries', $payload);

        return [$response, $payload];
    }

    public function test_dispatcher_queues_each_due_recipient_and_rechecks_database_after_queue_outage(): void
    {
        $enquiry = $this->enquiry();
        $deliveryA = $this->delivery($enquiry, '9223372036854775807');
        $deliveryB = $enquiry->outboxEvent->deliveries()->create(['chat_id_snapshot' => '-1001234567890', 'recipient_name_snapshot' => 'Negative', 'status' => 'pending']);
        Bus::fake();

        $this->artisan('enquiries:dispatch-telegram')->assertSuccessful();
        Bus::assertDispatched(DeliverEnquiryToTelegram::class, 2);

        // Jobs do not mutate the durable pending rows while Redis has not consumed them.
        $this->assertSame('pending', $deliveryA->fresh()->status);
        $this->assertSame('pending', $deliveryB->fresh()->status);
    }

    public function test_successful_delivery_uses_signed_string_chat_id_and_sent_rows_are_idempotent(): void
    {
        $this->settings();
        $delivery = $this->delivery($this->enquiry());
        Http::fake(['https://api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 991]], 200)]);

        (new DeliverEnquiryToTelegram($delivery->id))->handle(app(TelegramBotClient::class));
        (new DeliverEnquiryToTelegram($delivery->id))->handle(app(TelegramBotClient::class));

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['chat_id'] === '-1001234567890' && str_contains($request['text'], 'Reference:'));
        $this->assertDatabaseHas('enquiry_deliveries', ['id' => $delivery->id, 'status' => 'sent', 'telegram_message_id' => '991', 'attempts' => 1]);
    }

    public function test_telegram_message_uses_stored_product_snapshot_without_live_product_lookup(): void
    {
        $this->settings();
        $enquiry = $this->enquiry(['type' => 'product_enquiry']);
        $enquiry->items()->create([
            'product_identifier' => '3bd329e6-0ee8-4eb6-a7dc-66a6f2e2d8fa',
            'product_public_id' => '3bd329e6-0ee8-4eb6-a7dc-66a6f2e2d8fa',
            'brand_public_id' => 'abfe0638-4385-491b-bc6b-5a77c3fa89ea',
            'sku_snapshot' => 'HIST-01', 'brand_name_snapshot' => 'Historical Brand', 'quantity' => 2,
            'unit_price_aed' => null, 'currency' => 'AED', 'availability_snapshot' => true,
            'snapshot_at' => now(), 'product_snapshot' => ['name' => 'Archived display name'], 'position' => 0,
        ]);
        $delivery = $this->delivery($enquiry);
        Http::fake(['https://api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 992]], 200)]);

        (new DeliverEnquiryToTelegram($delivery->id))->handle(app(TelegramBotClient::class));

        Http::assertSent(fn ($request) => str_contains($request['text'], 'Archived display name')
            && str_contains($request['text'], 'HIST-01') && str_contains($request['text'], 'Historical Brand')
            && str_contains($request['text'], 'Price on enquiry'));
        $this->assertDatabaseHas('enquiry_deliveries', ['id' => $delivery->id, 'status' => 'sent']);
    }

    public function test_rate_limit_retries_with_retry_after_and_permanent_client_error_fails(): void
    {
        $this->settings();
        $retry = $this->delivery($this->enquiry());
        Http::fake(['https://api.telegram.org/*/sendMessage' => Http::sequence()
            ->push(['ok' => false, 'parameters' => ['retry_after' => 42]], 429)
            ->push(['ok' => false], 403)
            ->push(['ok' => false], 503)]);
        (new DeliverEnquiryToTelegram($retry->id))->handle(app(TelegramBotClient::class));
        $this->assertDatabaseHas('enquiry_deliveries', ['id' => $retry->id, 'status' => 'pending', 'last_error_code' => 'telegram_rate_limited']);
        $this->assertTrue($retry->fresh()->next_attempt_at->between(now()->addSeconds(41), now()->addSeconds(43)));

        $permanent = $this->delivery($this->enquiry());
        (new DeliverEnquiryToTelegram($permanent->id))->handle(app(TelegramBotClient::class));
        $this->assertDatabaseHas('enquiry_deliveries', ['id' => $permanent->id, 'status' => 'failed', 'last_error_code' => 'telegram_rejected']);

        $temporary = $this->delivery($this->enquiry());
        (new DeliverEnquiryToTelegram($temporary->id))->handle(app(TelegramBotClient::class));
        $this->assertDatabaseHas('enquiry_deliveries', ['id' => $temporary->id, 'status' => 'pending', 'last_error_code' => 'telegram_temporary']);
    }

    public function test_expired_lease_is_requeued_and_retry_command_resets_terminal_delivery(): void
    {
        $delivery = $this->delivery($this->enquiry());
        $delivery->update(['status' => 'sending', 'lease_token' => (string) Str::uuid(), 'lease_expires_at' => now()->subMinute()]);
        Bus::fake();
        $this->artisan('enquiries:dispatch-telegram')->assertSuccessful();
        Bus::assertDispatched(DeliverEnquiryToTelegram::class, fn ($job) => $job->deliveryId === $delivery->id);

        $delivery->update(['status' => 'failed', 'attempts' => 8, 'safe_error' => 'temporary failure']);
        $this->artisan('enquiries:retry-failed-telegram', ['--event' => (string) $delivery->outbox_event_id])->assertSuccessful();
        $this->assertDatabaseHas('enquiry_deliveries', ['id' => $delivery->id, 'status' => 'pending', 'attempts' => 0, 'safe_error' => null]);
    }

    public function test_database_outbox_stays_pending_when_redis_dispatch_fails(): void
    {
        $delivery = $this->delivery($this->enquiry());
        $dispatcher = \Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('redis unavailable'));
        app()->instance(Dispatcher::class, $dispatcher);

        $this->artisan('enquiries:dispatch-telegram')->assertSuccessful();
        $this->assertDatabaseHas('enquiry_deliveries', ['id' => $delivery->id, 'status' => 'pending']);
    }

    public function test_catalogue_request_issues_hash_only_grant_and_can_be_recovered_by_idempotency_replay(): void
    {
        [$first, $payload] = $this->submitCatalogue();
        $first->assertCreated()->assertJsonPath('data.downloadGrant.downloadPath', '/api/v1/catalogues/download');
        $this->assertStringNotContainsString('catalogues/guide.pdf', $first->getContent());
        $token = $first->json('data.downloadGrant.token');
        $grant = CatalogueDownloadGrant::query()->firstOrFail();
        $this->assertSame(hash('sha256', $token), $grant->token_hash);
        $this->assertNotSame($token, $grant->token_hash);
        $this->assertSame(64, strlen($token));

        $replay = $this->withHeader('Idempotency-Key', 'catalogue-request-00001')->postJson('/api/v1/enquiries', $payload);
        $replay->assertOk();
        $rotatedToken = $replay->json('data.downloadGrant.token');
        $this->assertNotSame($token, $rotatedToken);
        $this->assertDatabaseCount('enquiries', 1);
        $this->assertDatabaseCount('catalogue_download_grants', 2);

        $download = $this->withHeaders(['Authorization' => 'Bearer '.$rotatedToken, 'X-Catalogue-Enquiry' => $replay->json('data.enquiryId')])
            ->get('/api/v1/catalogues/download')->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Cache-Control', 'max-age=0, no-cache, no-store, private');
        $this->assertSame('%PDF-1.4 private guide', $download->streamedContent());
        $this->withHeaders(['Authorization' => 'Bearer '.$rotatedToken, 'X-Catalogue-Enquiry' => $replay->json('data.enquiryId')])
            ->get('/api/v1/catalogues/download')->assertOk();
        $this->assertSame(2, CatalogueDownloadGrant::query()->where('token_hash', hash('sha256', $rotatedToken))->value('download_count'));
        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Catalogue-Enquiry' => $replay->json('data.enquiryId')])
            ->get('/api/v1/catalogues/download')->assertOk();

        $this->withHeader('Idempotency-Key', 'catalogue-request-00001')
            ->postJson('/api/v1/enquiries', array_merge($payload, ['role' => 'developer']))->assertStatus(409);
    }

    public function test_catalogue_grant_rejects_invalid_expired_and_unavailable_files(): void
    {
        [$response] = $this->submitCatalogue();
        $token = $response->json('data.downloadGrant.token');
        $enquiryId = $response->json('data.enquiryId');
        $this->withHeaders(['Authorization' => 'Bearer '.str_repeat('a', 64), 'X-Catalogue-Enquiry' => $enquiryId])
            ->get('/api/v1/catalogues/download')->assertNotFound();

        CatalogueDownloadGrant::query()->firstOrFail()->update(['expires_at' => now()->subSecond()]);
        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Catalogue-Enquiry' => $enquiryId])
            ->get('/api/v1/catalogues/download')->assertStatus(410)->assertJsonPath('error.code', 'download_grant_expired');

        Storage::disk('local')->delete('catalogues/guide.pdf');
        $this->withHeader('Idempotency-Key', 'catalogue-request-00001')->postJson('/api/v1/enquiries', [
            'type' => 'catalogue_download', 'name' => 'Taylor Example', 'phone' => '+971500000000', 'email' => 'taylor@example.com',
            'catalogueId' => DB::table('catalogues')->value('public_id'), 'role' => 'architect',
        ])->assertNotFound();
    }
}
