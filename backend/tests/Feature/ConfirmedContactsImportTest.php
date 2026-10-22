<?php

namespace Tests\Feature;

use App\Models\Showroom;
use App\Models\SiteContactSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfirmedContactsImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_write_and_apply_is_repeatable_without_overwriting_manual_edits(): void
    {
        $this->artisan('contacts:import-confirmed')->expectsOutputToContain('Would insert showroom: deva-harmony-trading')->assertExitCode(0);
        $this->assertDatabaseCount('showrooms', 0);

        $this->artisan('contacts:import-confirmed', ['--apply' => true])->assertExitCode(0);
        $this->assertDatabaseCount('showrooms', 3);
        $this->assertDatabaseHas('showrooms', ['public_id' => 'design-house-projects', 'emails' => '[]']);
        $this->assertDatabaseHas('site_contact_settings', [
            'id' => 1,
            'whatsapp_url' => 'https://api.whatsapp.com/send/?phone=971585802562&text&type=phone_number&app_absent=0',
        ]);
        $this->assertSame('deva-harmony-trading', Showroom::query()->find(SiteContactSetting::query()->findOrFail(1)->primary_showroom_id)->public_id);

        Showroom::query()->where('public_id', 'real-touch-flowers')->update(['description' => 'Manually changed']);
        $this->artisan('contacts:import-confirmed')->expectsOutputToContain('Conflict: existing showroom differs')->assertExitCode(1);
        $this->artisan('contacts:import-confirmed', ['--apply' => true])->assertExitCode(1);
        $this->assertDatabaseHas('showrooms', ['public_id' => 'real-touch-flowers', 'description' => 'Manually changed']);
    }
}
