<?php

namespace Tests\Feature;

use App\Models\PageSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PageSeoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_seo_seed_is_idempotent_and_preserves_editor_changes(): void
    {
        $this->seed();
        PageSeo::query()->where('page_key', 'about')->update(['title' => 'Edited title']);

        $this->seed();

        $this->assertDatabaseCount('page_seos', 8);
        $this->assertDatabaseHas('page_seos', ['page_key' => 'about', 'title' => 'Edited title']);
    }

    public function test_public_page_seo_endpoint_only_exposes_the_contract_fields(): void
    {
        $this->seed();

        $response = $this->getJson('/api/v1/page-seo/about');

        $response->assertOk()->assertJsonPath('data.pageKey', 'about')
            ->assertJsonPath('data.title', 'About Pots & Planters | Planters & Greenery for Projects')
            ->assertJsonPath('data.ogImageUrl', null)
            ->assertJsonStructure(['data' => ['pageKey', 'title', 'description', 'ogImageUrl']]);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame(['pageKey', 'title', 'description', 'ogImageUrl'], array_keys($response->json('data')));
    }

    public function test_news_page_has_seeded_seo_data(): void
    {
        $this->seed();

        $this->getJson('/api/v1/page-seo/news')->assertOk()
            ->assertJsonPath('data.pageKey', 'news')
            ->assertJsonPath('data.title', 'News & Insights | Pots & Planters');
    }

    public function test_unknown_page_key_returns_404(): void
    {
        $this->getJson('/api/v1/page-seo/news-article')->assertNotFound();
    }

    public function test_og_image_response_contains_public_url_not_storage_path(): void
    {
        $this->seed();
        Storage::fake('public');
        Storage::disk('public')->put('page-seo/share.png', 'fake-image-content');
        PageSeo::query()->where('page_key', 'about')->update(['og_image_path' => 'page-seo/share.png']);

        $response = $this->getJson('/api/v1/page-seo/about')->assertOk();
        $imageUrl = $response->json('data.ogImageUrl');

        $this->assertStringContainsString('/storage/page-seo/share.png', $imageUrl);
        $this->assertStringNotContainsString('/var/www', $imageUrl);
        $this->assertStringNotContainsString('page-seos', $imageUrl);
    }
}
