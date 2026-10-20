<?php

namespace Database\Seeders;

use App\Models\PageSeo;
use Illuminate\Database\Seeder;

class PageSeoSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            'home' => ['Pots & Planters for Projects across the UAE | Dubai', 'Pots, planters and artificial greenery for residential and commercial projects across the UAE. Discuss your project requirements with our Dubai team.'],
            'shop' => ['Planters & Artificial Plants Supplier in UAE | Pots & Planters', 'Explore pots, planters and artificial greenery.'],
            'solutions' => ['Planters & Greenery Solutions for Hotels, Offices & Villas | Pots & Planters', 'Explore planters and artificial greenery for hotels, restaurants, offices, villas and commercial spaces in Dubai and the UAE. Discover interior concepts and request a quotation.'],
            'catalogues' => ['Product Catalogues | Pots & Planters UAE', 'Explore catalogues of outdoor and indoor planters, artificial plants and trees for hospitality, commercial and residential projects across the UAE.'],
            'news' => ['News & Insights | Pots & Planters', 'Explore the Pots & Planters news archive: project announcements, company updates and exhibitions from 2023–2025 in Dubai and the UAE.'],
            'about' => ['About Pots & Planters | Planters & Greenery for Projects', 'Discover how Pots & Planters helps architects, designers and project buyers explore indoor and outdoor planters and artificial greenery for projects in the UAE.'],
            'contacts' => ['Contact Us | Pots & Planters', 'Discuss planters and artificial greenery for your project. Contact our team and explore showroom contacts in Dubai, UAE and Riyadh, Saudi Arabia.'],
            'private_policy' => ['Privacy Policy | Pots & Planters', 'Learn how Pots & Planters handles information related to website enquiries, product selections, catalogue requests and essential website storage.'],
        ];

        foreach ($pages as $pageKey => [$title, $description]) {
            PageSeo::query()->firstOrCreate(['page_key' => $pageKey], [
                'title' => $title,
                'description' => $description,
            ]);
        }
    }
}
