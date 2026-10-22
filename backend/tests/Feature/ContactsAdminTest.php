<?php

namespace Tests\Feature;

use App\Filament\Resources\ShowroomResource\Pages\CreateShowroom;
use App\Filament\Resources\ShowroomResource\Pages\EditShowroom;
use App\Filament\Resources\SiteContactSettingResource\Pages\EditSiteContactSettings;
use App\Models\Showroom;
use App\Models\SiteContactSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContactsAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_can_create_and_edit_showroom_with_ordered_contacts(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Livewire::actingAs($admin)->test(CreateShowroom::class)
            ->set('data.public_id', 'admin-showroom')->set('data.name', 'Admin Showroom')
            ->set('data.status', 'published')->set('data.address', 'Address')->set('data.city', 'Dubai')->set('data.country', 'UAE')
            ->set('data.phones', [['label' => 'First', 'href' => 'tel:+971111111111'], ['label' => 'Second', 'href' => 'tel:+971222222222']])
            ->set('data.emails', [['email' => 'one@example.com'], ['email' => 'two@example.com']])->set('data.sort_order', 3)
            ->call('create')->assertHasNoFormErrors();
        $showroom = Showroom::query()->where('public_id', 'admin-showroom')->firstOrFail();
        $this->assertSame(['one@example.com', 'two@example.com'], $showroom->emails);
        $this->assertSame(['First', 'Second'], array_column($showroom->phones, 'label'));

        Livewire::actingAs($admin)->test(EditShowroom::class, ['record' => $showroom->id])
            ->set('data.status', 'draft')->call('save')->assertHasNoFormErrors();
        $this->assertSame('draft', $showroom->fresh()->status);
    }

    public function test_admin_cannot_publish_incomplete_showroom_and_can_edit_singleton_settings(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Livewire::actingAs($admin)->test(CreateShowroom::class)
            ->set('data.public_id', 'incomplete')->set('data.status', 'published')->set('data.sort_order', 0)
            ->call('create')->assertHasFormErrors();
        $this->assertDatabaseMissing('showrooms', ['public_id' => 'incomplete']);

        $primary = Showroom::query()->create([
            'public_id' => 'primary', 'name' => 'Primary', 'address' => 'Address', 'city' => 'Dubai',
            'country' => 'UAE', 'phones' => [], 'emails' => [], 'status' => 'published', 'sort_order' => 0,
        ]);
        Livewire::actingAs($admin)->test(EditSiteContactSettings::class, ['record' => '1'])
            ->set('data.primary_showroom_id', $primary->id)
            ->set('data.whatsapp_url', 'https://example.com/wa')
            ->call('save')->assertHasNoFormErrors();
        $settings = SiteContactSetting::query()->findOrFail(1);
        $this->assertSame($primary->id, $settings->primary_showroom_id);
        $this->assertSame('https://example.com/wa', $settings->whatsapp_url);
    }
}
