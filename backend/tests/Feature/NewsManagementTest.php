<?php

namespace Tests\Feature;

use App\Filament\Resources\NewsArticleResource\Pages\CreateNewsArticle;
use App\Filament\Resources\NewsArticleResource\Pages\EditNewsArticle;
use App\Filament\Resources\NewsCategoryResource\Pages\CreateNewsCategory;
use App\Filament\Resources\NewsCategoryResource\Pages\EditNewsCategory;
use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class NewsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_can_manage_categories_and_cannot_delete_a_category_with_articles(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)->test(CreateNewsCategory::class)
            ->set('data.name', 'Projects & Collaborations')
            ->set('data.sort_order', 10)
            ->call('create')
            ->assertHasNoFormErrors();
        $category = NewsCategory::query()->where('slug', 'projects-collaborations')->firstOrFail();

        Livewire::actingAs($admin)->test(EditNewsCategory::class, ['record' => $category->getKey()])
            ->set('data.name', 'Updated projects')
            ->set('data.slug', 'updated-projects')
            ->set('data.is_active', false)
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertDatabaseHas('news_categories', ['id' => $category->id, 'slug' => 'updated-projects', 'is_active' => false]);

        $article = NewsArticle::query()->create(['category_id' => $category->id, 'title' => 'Draft', 'slug' => 'draft']);
        Livewire::actingAs($admin)->test(EditNewsCategory::class, ['record' => $category->getKey()])
            ->callAction('delete')
            ->assertHasNoActionErrors();
        $this->assertDatabaseHas('news_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('news_articles', ['id' => $article->id]);
    }

    public function test_admin_can_create_drafts_edit_slug_and_save_rich_editor_html(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = NewsCategory::query()->create(['name' => 'Company News', 'slug' => 'company-news', 'is_active' => true]);
        Livewire::actingAs($admin)->test(CreateNewsArticle::class)
            ->set('data.title', 'A Draft Story')
            ->set('data.category_id', $category->id)
            ->set('data.status', 'draft')
            ->call('create')
            ->assertHasNoFormErrors();
        $article = NewsArticle::query()->where('slug', 'a-draft-story')->firstOrFail();

        Livewire::actingAs($admin)->test(EditNewsArticle::class, ['record' => $article->getKey()])
            ->set('data.title', 'A Renamed Draft Story')
            ->set('data.slug', 'editorial-url')
            ->set('data.body', '<h2>Heading</h2><p><strong>Bold</strong> <a href="https://example.com">link</a></p><script>alert(1)</script>')
            ->call('save')
            ->assertHasNoFormErrors();
        $article->refresh();
        $this->assertSame('editorial-url', $article->slug);
        $this->assertStringContainsString('<h2>Heading</h2>', $article->body);
        $this->assertStringContainsString('href="https://example.com"', $article->body);
        $this->assertStringNotContainsString('<script', $article->body);

        Livewire::actingAs($admin)->test(EditNewsArticle::class, ['record' => $article->getKey()])
            ->set('data.status', 'published')
            ->call('save')
            ->assertHasFormErrors(['excerpt', 'published_at', 'cover_path', 'seo_title']);
        $this->assertSame('draft', $article->fresh()->status);
    }

    public function test_unsaved_article_preview_tracks_form_state_and_sanitizes_rich_text(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = NewsCategory::query()->create(['name' => 'Company News', 'slug' => 'company-news', 'is_active' => true]);
        Storage::disk('public')->put('news/live-preview.jpg', 'image');

        Livewire::actingAs($admin)->test(CreateNewsArticle::class)
            ->set('data.title', 'Live unsaved story')
            ->set('data.slug', 'live-unsaved-story')
            ->set('data.category_id', $category->id)
            ->set('data.published_at', '2026-10-22')
            ->set('data.excerpt', 'Visible preview excerpt')
            ->set('data.cover_path', 'news/live-preview.jpg')
            ->set('data.body', '<h2>Visible preview heading</h2><p><strong>Safe formatting</strong> <a href="javascript:alert(1)">unsafe link</a></p><script>alert(1)</script>')
            ->assertSee('Live unsaved story')
            ->assertSee('Company News')
            ->assertSee('22 October 2026')
            ->assertSee('news/live-preview.jpg')
            ->assertSee('Visible preview excerpt')
            ->assertSee('Visible preview heading')
            ->assertSee('Safe formatting')
            ->assertDontSee('javascript:')
            ->assertDontSee('alert(1)');

        $this->assertDatabaseCount('news_articles', 0);
    }

    public function test_historical_import_is_dry_run_idempotent_and_preserves_editor_changes(): void
    {
        $this->assertSame(0, Artisan::call('news:import-historical'));
        $this->assertSame(0, NewsArticle::query()->count());

        $this->assertSame(0, Artisan::call('news:import-historical', ['--apply' => true]));
        $this->assertSame(7, NewsArticle::query()->count());
        $this->assertSame(3, NewsCategory::query()->count());
        $this->assertSame(['news-6', 'news-7'], NewsArticle::query()->whereDate('published_at', '2025-10-30')->orderBy('id')->pluck('public_id')->all());
        $snapshot = json_decode((string) file_get_contents(base_path('database/fixtures/news/historical-news.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($snapshot as $source) {
            $article = NewsArticle::query()->where('public_id', $source['id'])->firstOrFail();
            $this->assertSame($source['slug'], $article->slug);
            $this->assertSame($source['title'], $article->title);
            $this->assertSame($source['excerpt'], $article->excerpt);
            $this->assertSame($source['publishedAt'], $article->published_at->toDateString());
            $this->assertSame($source['seoTitle'], $article->seo_title);
            $this->assertSame($source['seoDescription'], $article->seo_description);
            $this->assertSame($source['coverAlt'], $article->cover_alt);
            $this->assertSame($source['category'], $article->category->name);
            $this->assertSame('news/'.$source['image'], $article->cover_path);
            $this->assertSame($source['imageSha256'], hash('sha256', Storage::disk('public')->get($article->cover_path)));
            $this->assertSame($source['imageSha256'], hash_file('sha256', base_path('database/fixtures/news/images/'.$source['image'])));
        }
        $this->assertDatabaseHas('news_articles', ['public_id' => 'news-5', 'cover_path' => 'news/5_new.png']);
        $this->getJson('/api/v1/news')->assertOk()->assertJsonCount(7, 'data');
        $this->assertStringContainsString('https://greenpactlab.io/', NewsArticle::query()->where('public_id', 'news-2')->value('body'));
        $this->assertStringContainsString('https://www.jumeirah.com/stay/dubai/jumeirah-marsa-al-arab', NewsArticle::query()->where('public_id', 'news-6')->value('body'));

        $edited = NewsArticle::query()->where('public_id', 'news-1')->firstOrFail();
        $edited->update(['title' => 'Editor changed this title', 'slug' => 'editor-changed-slug', 'seo_title' => 'Editor SEO']);
        $this->assertSame(0, Artisan::call('news:import-historical', ['--apply' => true]));
        $this->assertSame(7, NewsArticle::query()->count());
        $this->assertSame(3, NewsCategory::query()->count());
        $this->assertSame('Editor changed this title', $edited->fresh()->title);
        $this->assertSame('editor-changed-slug', $edited->fresh()->slug);
        $this->assertSame('Editor SEO', $edited->fresh()->seo_title);
        $this->assertSame(7, count(Storage::disk('public')->allFiles('news')));
    }

    public function test_historical_import_reuses_an_existing_image_only_when_its_checksum_matches(): void
    {
        $source = base_path('database/fixtures/news/images/1.jpg');
        $contents = (string) file_get_contents($source);
        Storage::disk('public')->put('news/1.jpg', $contents);

        $this->assertSame(0, Artisan::call('news:import-historical'));
        $this->assertSame(0, Artisan::call('news:import-historical', ['--apply' => true]));
        $this->assertSame(hash_file('sha256', $source), hash('sha256', Storage::disk('public')->get('news/1.jpg')));
        $this->assertDatabaseHas('news_articles', ['public_id' => 'news-1', 'cover_path' => 'news/1.jpg']);
        $this->assertSame(7, NewsArticle::query()->count());
    }

    public function test_historical_import_rejects_an_existing_image_with_different_contents(): void
    {
        Storage::disk('public')->put('news/1.jpg', 'not the historical source');

        $this->assertSame(1, Artisan::call('news:import-historical'));
        $this->assertSame(0, NewsArticle::query()->count());
    }
}
