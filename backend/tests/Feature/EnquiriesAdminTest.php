<?php

namespace Tests\Feature;

use App\Filament\Pages\TelegramSettings;
use App\Filament\Resources\EnquiryResource;
use App\Filament\Resources\EnquiryResource\Pages\ListEnquiries;
use App\Filament\Resources\EnquiryResource\Pages\ViewEnquiry;
use App\Models\Enquiry;
use App\Models\TelegramRecipient;
use App\Models\TelegramSetting;
use App\Models\User;
use App\Services\EnquiryWorkflow;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class EnquiriesAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function enquiry(array $overrides = []): Enquiry
    {
        return Enquiry::query()->create(array_merge([
            'type' => 'contact', 'status' => 'new', 'name' => '<script>alert(1)</script>',
            'phone' => '+971500000000', 'email' => 'person@example.com', 'message' => '<b>hello</b>',
            'submitted_at' => now(),
        ], $overrides));
    }

    public function test_admin_can_review_enquiries_and_change_status_with_immutable_actor_history(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $enquiry = $this->enquiry();

        Livewire::actingAs($admin)->test(ListEnquiries::class)->assertCanSeeTableRecords([$enquiry]);
        Livewire::actingAs($admin)->test(ViewEnquiry::class, ['record' => $enquiry->id])
            ->assertSee('<script>alert(1)</script>')
            ->assertActionExists('changeStatus')->assertActionExists('addNote');
        app(EnquiryWorkflow::class)->changeStatus($enquiry, 'in_progress', $admin->id);
        app(EnquiryWorkflow::class)->addNote($enquiry, 'Call back tomorrow', $admin->id);

        $this->assertSame('in_progress', $enquiry->fresh()->status);
        $this->assertDatabaseHas('enquiry_activities', ['enquiry_id' => $enquiry->id, 'actor_id' => $admin->id, 'kind' => 'status', 'old_status' => 'new', 'new_status' => 'in_progress']);
        $this->assertDatabaseHas('enquiry_activities', ['enquiry_id' => $enquiry->id, 'actor_id' => $admin->id, 'kind' => 'note', 'note' => 'Call back tomorrow']);
        $this->assertFalse(EnquiryResource::canDelete($enquiry));
    }

    public function test_admin_sees_historical_product_snapshot_and_null_price_label(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $enquiry = $this->enquiry(['type' => 'product_enquiry']);
        $enquiry->items()->create([
            'product_identifier' => '3bd329e6-0ee8-4eb6-a7dc-66a6f2e2d8fa',
            'product_public_id' => '3bd329e6-0ee8-4eb6-a7dc-66a6f2e2d8fa',
            'brand_public_id' => 'abfe0638-4385-491b-bc6b-5a77c3fa89ea',
            'sku_snapshot' => 'HIST-01', 'brand_name_snapshot' => 'Historical Brand', 'quantity' => 2,
            'unit_price_aed' => null, 'currency' => 'AED', 'availability_snapshot' => true,
            'snapshot_at' => now(), 'product_snapshot' => ['name' => 'Archived display name'], 'position' => 0,
        ]);

        Livewire::actingAs($admin)->test(ViewEnquiry::class, ['record' => $enquiry->id])
            ->assertSee('Historical Brand')->assertSee('HIST-01')->assertSee('Archived display name')
            ->assertSee('Price on enquiry');
    }

    public function test_non_admin_cannot_access_enquiry_or_settings_pages(): void
    {
        $this->get('/admin/enquiries')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get('/admin/enquiries')->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get('/admin/telegram-settings')->assertForbidden();
    }

    public function test_admin_can_save_encrypted_settings_and_multiple_signed_recipient_ids(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Livewire::actingAs($admin)->test(TelegramSettings::class)
            ->set('data.notifications_enabled', true)->set('data.bot_token', 'bot-secret')
            ->set('data.proxy_enabled', true)->set('data.proxy_host', 'squid.internal')->set('data.proxy_port', '3128')
            ->set('data.proxy_username', 'proxy-user')->set('data.proxy_password', 'proxy-secret')
            ->set('data.recipients', [
                ['name' => 'Person', 'chat_id' => '9223372036854775807', 'enabled' => true],
                ['name' => 'Supergroup', 'chat_id' => '-1001234567890', 'enabled' => true],
            ])->call('save')->assertHasNoFormErrors();

        $settings = TelegramSetting::query()->findOrFail(1);
        $this->assertSame('bot-secret', $settings->bot_token);
        $this->assertSame('proxy-secret', $settings->proxy_password);
        $this->assertNotSame('bot-secret', DB::table('telegram_settings')->value('bot_token'));
        $this->assertNotSame('proxy-secret', DB::table('telegram_settings')->value('proxy_password'));
        $this->assertDatabaseHas('telegram_recipients', ['chat_id' => '9223372036854775807']);
        $this->assertDatabaseHas('telegram_recipients', ['chat_id' => '-1001234567890']);

        Livewire::actingAs($admin)->test(TelegramSettings::class)
            ->set('data.notifications_enabled', false)->set('data.bot_token', '')
            ->set('data.proxy_enabled', false)->set('data.proxy_password', '')
            ->set('data.recipients', [])->call('save')->assertHasNoFormErrors();
        $this->assertSame('bot-secret', TelegramSetting::query()->findOrFail(1)->bot_token);
        $this->assertSame('proxy-secret', TelegramSetting::query()->findOrFail(1)->proxy_password);
        $this->assertSoftDeleted('telegram_recipients');
    }

    public function test_admin_connection_and_test_message_use_http_fake_without_creating_an_enquiry(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.telegram.org/*/getMe' => Http::response(['ok' => true, 'result' => ['first_name' => 'Test bot', 'username' => 'pots_test_bot']], 200),
            'https://api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 10]], 200),
        ]);
        TelegramSetting::query()->forceCreate([
            'id' => 1, 'notifications_enabled' => false, 'bot_token' => '123456:fake-token',
            'proxy_enabled' => true, 'proxy_host' => 'squid.internal', 'proxy_port' => 3128,
            'proxy_username' => 'proxy-user', 'proxy_password' => 'proxy-password',
        ]);
        $recipient = TelegramRecipient::query()->create(['name' => 'Supergroup', 'chat_id' => '-1001234567890', 'enabled' => true]);
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)->test(TelegramSettings::class)->call('testConnection');
        Livewire::actingAs($admin)->test(TelegramSettings::class)
            ->set('data.test_recipient_id', (string) $recipient->id)
            ->call('save')->assertSet('data.test_recipient_id', (string) $recipient->id)
            ->call('sendTestMessage');

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/getMe'));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sendMessage') && $request['chat_id'] === '-1001234567890');
        $this->assertDatabaseCount('enquiries', 0);
        $this->assertDatabaseCount('enquiry_outbox_events', 0);
    }
}
