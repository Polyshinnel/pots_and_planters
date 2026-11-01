<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use App\Models\TelegramRecipient;
use App\Models\TelegramSetting;
use App\Services\TelegramSettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EnquiriesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function contactPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'contact', 'name' => 'Taylor Example', 'phone' => '+971 50 000 0000',
            'email' => 'taylor@example.com', 'message' => 'Please contact me.',
        ], $overrides);
    }

    private function submit(array $payload, string $key = 'request-key-00000001')
    {
        return $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/enquiries', $payload);
    }

    public function test_contact_is_persisted_with_outbox_before_created_response(): void
    {
        $response = $this->submit($this->contactPayload(['source' => ['pagePath' => '/contacts', 'placement' => 'form']]));

        $response->assertCreated()->assertJsonPath('data.status', 'persisted')->assertJsonPath('data.notificationStatus', 'skipped_disabled');
        $this->assertDatabaseCount('enquiries', 1);
        $this->assertDatabaseCount('enquiry_outbox_events', 1);
        $this->assertDatabaseHas('enquiries', ['type' => 'contact', 'source_path' => '/contacts', 'source_placement' => 'form']);
        $this->assertDatabaseHas('enquiry_outbox_events', ['status' => 'skipped_disabled', 'notifications_enabled_snapshot' => false]);
    }

    public function test_idempotency_replays_same_result_and_rejects_changed_payload(): void
    {
        $first = $this->submit($this->contactPayload());
        $second = $this->submit($this->contactPayload(), 'request-key-00000002');
        $replay = $this->submit($this->contactPayload(), 'request-key-00000001');
        $conflict = $this->submit($this->contactPayload(['message' => 'Different content.']), 'request-key-00000001');

        $first->assertCreated();
        $second->assertCreated();
        $replay->assertOk()->assertJsonPath('data.enquiryId', $first->json('data.enquiryId'));
        $conflict->assertStatus(409)->assertJsonPath('error.code', 'idempotency_conflict');
        $this->assertDatabaseCount('enquiries', 2);
    }

    public function test_invalid_fields_and_honeypot_do_not_create_enquiries(): void
    {
        $this->submit($this->contactPayload(['email' => 'not-an-email']))
            ->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
        $this->submit($this->contactPayload(['website' => 'automated']))
            ->assertStatus(422);

        $this->assertDatabaseCount('enquiries', 0);
    }

    public function test_product_enquiry_is_rejected_until_authoritative_be08_catalogue_exists(): void
    {
        $this->submit($this->contactPayload([
            'type' => 'product_enquiry', 'productId' => 'client-id', 'sku' => 'UNVERIFIED', 'quantity' => 2,
        ]))->assertNotFound()->assertJsonPath('error.code', 'resource_unavailable');
        $this->submit($this->contactPayload([
            'type' => 'cart_enquiry', 'items' => [['productId' => 'client-id', 'sku' => 'UNVERIFIED', 'quantity' => 2]],
        ]), 'cart-key-0000000001')->assertNotFound()->assertJsonPath('error.code', 'resource_unavailable');

        $this->assertDatabaseCount('enquiries', 0);
    }

    public function test_catalogue_request_uses_only_published_database_snapshot(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('catalogues/sample.pdf', '%PDF-1.4 fake test PDF');
        $catalogueId = (string) Str::uuid();
        DB::table('catalogues')->insert([
            'public_id' => $catalogueId, 'slug' => 'sample', 'title' => 'Verified catalogue',
            'pdf_path' => 'catalogues/sample.pdf', 'cover_path' => 'catalogues/sample.webp',
            'status' => 'published', 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->submit($this->contactPayload([
            'type' => 'catalogue_download', 'catalogueId' => $catalogueId, 'role' => 'architect',
        ]))->assertCreated()->assertJsonPath('data.downloadGrant.downloadPath', '/api/v1/catalogues/download');

        $enquiry = Enquiry::query()->firstOrFail();
        $this->assertSame('Verified catalogue', $enquiry->catalogue_snapshot['title']);
        $this->assertSame($catalogueId, $enquiry->catalogue_snapshot['publicId']);
    }

    public function test_each_active_signed_chat_id_is_snapshotted_per_recipient(): void
    {
        TelegramSetting::query()->forceCreate(['id' => 1, 'notifications_enabled' => true]);
        TelegramRecipient::query()->create(['name' => 'Private', 'chat_id' => '123456789', 'enabled' => true]);
        TelegramRecipient::query()->create(['name' => 'Supergroup', 'chat_id' => '-1001234567890', 'enabled' => true]);
        TelegramRecipient::query()->create(['name' => 'Disabled', 'chat_id' => '-987654321', 'enabled' => false]);

        $response = $this->submit($this->contactPayload());
        $response->assertCreated()->assertJsonPath('data.notificationStatus', 'pending');
        $this->assertDatabaseCount('enquiry_deliveries', 2);
        $this->assertDatabaseHas('enquiry_deliveries', ['chat_id_snapshot' => '123456789', 'recipient_name_snapshot' => 'Private']);
        $this->assertDatabaseHas('enquiry_deliveries', ['chat_id_snapshot' => '-1001234567890', 'recipient_name_snapshot' => 'Supergroup']);

        $recipient = TelegramRecipient::query()->where('chat_id', '-1001234567890')->firstOrFail();
        $recipient->update(['name' => 'Renamed']);
        $this->assertDatabaseHas('enquiry_deliveries', ['chat_id_snapshot' => '-1001234567890', 'recipient_name_snapshot' => 'Supergroup']);
    }

    public function test_no_enabled_recipients_does_not_block_enquiry_persistence(): void
    {
        TelegramSetting::query()->forceCreate(['id' => 1, 'notifications_enabled' => true]);

        $this->submit($this->contactPayload())->assertCreated()->assertJsonPath('data.notificationStatus', 'pending_no_recipients');
        $this->assertDatabaseCount('enquiries', 1);
        $this->assertDatabaseCount('enquiry_deliveries', 0);
    }

    public function test_invalid_signed_chat_ids_are_rejected_without_float_or_overflow_coercion(): void
    {
        foreach (['0', '9223372036854775808', '-9223372036854775809', '1.0', '1e3', 1.5] as $chatId) {
            try {
                TelegramRecipient::query()->create(['name' => 'Invalid', 'chat_id' => $chatId]);
                $this->fail("Expected chat ID {$chatId} to be rejected.");
            } catch (ValidationException) {
                $this->assertDatabaseMissing('telegram_recipients', ['chat_id' => $chatId]);
            }
        }
    }

    public function test_telegram_secrets_are_encrypted_in_storage_and_hidden_from_serialization(): void
    {
        $settings = TelegramSetting::query()->forceCreate([
            'id' => 1, 'bot_token' => 'secret-token', 'proxy_password' => 'secret-password',
        ]);
        $raw = DB::table('telegram_settings')->where('id', 1)->first();

        $this->assertNotSame('secret-token', $raw->bot_token);
        $this->assertNotSame('secret-password', $raw->proxy_password);
        $this->assertSame('secret-token', $settings->bot_token);
        $this->assertSame('secret-password', $settings->proxy_password);
        $this->assertArrayNotHasKey('bot_token', $settings->toArray());
        $this->assertArrayNotHasKey('proxy_password', $settings->toArray());
    }

    public function test_telegram_settings_manager_keeps_blank_secrets_and_rejects_proxy_urls(): void
    {
        $manager = app(TelegramSettingsManager::class);
        $manager->save(['bot_token' => 'bot-secret', 'notifications_enabled' => true]);
        $manager->save(['bot_token' => '', 'proxy_enabled' => true, 'proxy_host' => 'squid.internal', 'proxy_port' => 3128]);

        $settings = TelegramSetting::query()->findOrFail(1);
        $this->assertSame('bot-secret', $settings->bot_token);
        $this->assertTrue($settings->proxy_enabled);
        $this->assertSame('squid.internal', $settings->proxy_host);
        $this->assertSame(3128, $settings->proxy_port);

        $this->expectException(ValidationException::class);
        $manager->save(['proxy_host' => 'http://127.0.0.1:3128/path']);
    }

    public function test_missing_idempotency_key_and_non_json_payload_are_rejected(): void
    {
        $this->postJson('/api/v1/enquiries', $this->contactPayload())
            ->assertStatus(422)->assertJsonPath('error.code', 'idempotency_key_required');
        $this->call('POST', '/api/v1/enquiries', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{broken')
            ->assertStatus(400)->assertJsonPath('error.code', 'invalid_json');

        $this->assertDatabaseCount('enquiries', 0);
    }

    public function test_rate_limit_returns_safe_429_without_creating_an_extra_enquiry(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->submit($this->contactPayload(), 'rate-limit-key-000'.$attempt)->assertCreated();
        }

        $this->submit($this->contactPayload(), 'rate-limit-key-0006')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'rate_limited')
            ->assertHeader('Retry-After');
        $this->assertDatabaseCount('enquiries', 5);
    }
}
