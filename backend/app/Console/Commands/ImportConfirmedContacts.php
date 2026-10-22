<?php

namespace App\Console\Commands;

use App\Models\Showroom;
use App\Models\SiteContactSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportConfirmedContacts extends Command
{
    protected $signature = 'contacts:import-confirmed {--apply : Apply inserts for missing confirmed records}';

    protected $description = 'Dry-run or safely insert the three owner-confirmed showroom records and site contact settings';

    private const WHATSAPP = 'https://api.whatsapp.com/send/?phone=971585802562&text&type=phone_number&app_absent=0';

    /** @return list<array<string, mixed>> */
    private function records(): array
    {
        return [
            ['public_id' => 'deva-harmony-trading', 'name' => 'DEVA HARMONY TRADING', 'description' => 'Main Dubai showroom / warehouse.', 'address' => 'Warehouse#4, Al Kahraman Street#4, Al Quoz Industrial 4, Dubai', 'city' => 'Dubai', 'country' => 'UAE', 'phones' => [['label' => '+971 58 580 7443', 'href' => 'tel:+971585807443'], ['label' => '+971 58 520 2572', 'href' => 'tel:+971585202572']], 'emails' => ['pots.decor@potsandplanters.ae', 'uaepots@potsandplanters.ae'], 'instagram_handle' => '@plantersuae', 'instagram_url' => 'https://www.instagram.com/plantersuae', 'status' => 'published', 'sort_order' => 0],
            ['public_id' => 'real-touch-flowers', 'name' => 'Real Touch Flowers', 'description' => 'Our second showroom, Real Touch Flowers, is dedicated exclusively to premium artificial plants and trees by Real Touch.', 'address' => 'UAE, Dubai, Jabal Ali Industrial First, Sheikh Saeed Bin Mohd Bin Hasher Ware House, Warehouse No. 163', 'city' => 'Dubai', 'country' => 'UAE', 'phones' => [['label' => '+971 58 520 2572', 'href' => 'tel:+971585202572']], 'emails' => ['manager.realtouchtrees@gmail.com'], 'instagram_handle' => '@realtouch.uae', 'instagram_url' => 'https://www.instagram.com/realtouch.uae', 'status' => 'published', 'sort_order' => 1],
            ['public_id' => 'design-house-projects', 'name' => 'Design House Projects', 'description' => 'Our third showroom, Design House Projects, is dedicated exclusively to premium artificial plants and trees.', 'address' => 'RHGA4460, 6824 Al Imam Saud Ibn Abdul Aziz Branch Rd, 4460, Riyadh 12282', 'city' => 'Riyadh', 'country' => 'Saudi Arabia', 'phones' => [['label' => '+966 54 477 7707', 'href' => 'tel:+966544777707']], 'emails' => [], 'instagram_handle' => '@designhouseprojects', 'instagram_url' => 'https://www.instagram.com/designhouseprojects/', 'status' => 'published', 'sort_order' => 2],
        ];
    }

    public function handle(): int
    {
        $changes = [];
        $conflicts = [];
        foreach ($this->records() as $attributes) {
            $existing = Showroom::query()->where('public_id', $attributes['public_id'])->first();
            if (! $existing) {
                $changes[] = 'Would insert showroom: '.$attributes['public_id'];

                continue;
            }
            $current = $existing->only(array_keys($attributes));
            foreach (['phones', 'emails'] as $jsonField) {
                $current[$jsonField] = $existing->{$jsonField} ?? [];
            }
            if ($current !== $attributes) {
                $conflicts[] = 'Conflict: existing showroom differs from confirmed source: '.$attributes['public_id'];
            } else {
                $changes[] = 'Already matches: '.$attributes['public_id'];
            }
        }

        $settings = SiteContactSetting::query()->find(1);
        $deva = Showroom::query()->where('public_id', 'deva-harmony-trading')->first();
        if ($settings) {
            if ($settings->primary_showroom_id !== null && $settings->primary_showroom_id !== $deva?->id) {
                $conflicts[] = 'Conflict: primary showroom setting is already assigned to a different record.';
            } elseif ($settings->whatsapp_url !== null && $settings->whatsapp_url !== self::WHATSAPP) {
                $conflicts[] = 'Conflict: WhatsApp setting is already assigned a different URL.';
            } else {
                $changes[] = 'Would set missing primary showroom to DEVA and missing WhatsApp URL to the confirmed URL.';
            }
        } else {
            $conflicts[] = 'Conflict: singleton site contact settings row is missing.';
        }

        foreach ($changes as $change) {
            $this->line($change);
        }
        foreach ($conflicts as $conflict) {
            $this->error($conflict);
        }
        if ($conflicts !== []) {
            return self::FAILURE;
        }
        if (! $this->option('apply')) {
            $this->info('Dry run only. Re-run with --apply to insert missing records and fill empty settings.');

            return self::SUCCESS;
        }

        DB::transaction(function (): void {
            $ids = [];
            foreach ($this->records() as $attributes) {
                $showroom = Showroom::query()->where('public_id', $attributes['public_id'])->first();
                if (! $showroom) {
                    $showroom = Showroom::query()->create($attributes);
                }
                $ids[$attributes['public_id']] = $showroom->id;
            }
            $settings = SiteContactSetting::query()->findOrFail(1);
            if ($settings->primary_showroom_id === null || $settings->whatsapp_url === null) {
                $settings->primary_showroom_id ??= $ids['deva-harmony-trading'];
                $settings->whatsapp_url ??= self::WHATSAPP;
                $settings->save();
            }
        });
        $this->info('Confirmed contact data applied safely.');

        return self::SUCCESS;
    }
}
