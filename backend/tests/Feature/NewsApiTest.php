<?php

namespace Tests\Feature;

use App\Models\NewsArticle;
use App\Models\NewsCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NewsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_list_only_returns_complete_published_articles_with_active_categories_in_stable_order(): void
    {
        $category = $this->category();
        $first = $this->published($category, ['slug' => 'historical-six', 'published_at' => '2025-10-30']);
        $second = $this->published($category, ['slug' => 'historical-seven', 'published_at' => '2025-10-30']);
        $this->published($category, ['slug' => 'older-article', 'published_at' => '2024-01-01']);
        $this->article($category, ['status' => 'draft', 'slug' => 'draft-article']);
        $this->article($category, ['status' => 'archived', 'slug' => 'archived-article']);
        $hiddenCategory = $this->category(['slug' => 'inactive-category']);
        $this->published($hiddenCategory, ['slug' => 'inactive-category-article']);
        $hiddenCategory->update(['is_active' => false]);

        $response = $this->getJson('/api/v1/news')->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $first->public_id)
            ->assertJsonPath('data.1.id', $second->public_id)
            ->assertJsonPath('data.0.slug', 'historical-six')
            ->assertJsonPath('data.0.category.name', $category->name)
            ->assertJsonPath('data.0.category.slug', $category->slug)
            ->assertJsonPath('data.0.coverImage', '/storage/news/historical-six.webp')
            ->assertJsonPath('data.0.publishedAt', '2025-10-30');

        $serialized = $response->getContent();
        foreach (['category_id', 'cover_path', 'og_image_path', 'seo_title', 'filesystem'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $serialized);
        }
        $this->assertArrayNotHasKey('body', $response->json('data.0'));
    }

    public function test_detail_returns_sanitized_html_and_public_image_urls_but_hidden_and_unknown_slugs_are_404(): void
    {
        $category = $this->category();
        $article = $this->published($category, [
            'slug' => 'safe-article',
            'body' => '<h2>Heading</h2><p><strong>Bold</strong> &amp; text <a href="https://example.com" target="_blank">link</a></p><script>alert(1)</script><iframe src="https://evil.test"></iframe><a href="javascript:alert(1)">bad</a>',
            'og_image_path' => 'news/og.webp',
        ]);

        $response = $this->getJson('/api/v1/news/safe-article')->assertOk()
            ->assertJsonPath('data.id', $article->public_id)
            ->assertJsonPath('data.ogImage', '/storage/news/og.webp');

        $body = $response->json('data.body');
        $this->assertStringContainsString('<h2>Heading</h2>', $body);
        $this->assertStringContainsString('<strong>Bold</strong>', $body);
        $this->assertStringContainsString('href="https://example.com"', $body);
        $this->assertStringContainsString('rel="noopener noreferrer"', $body);
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('<iframe', $body);
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringNotContainsString('&amp;amp;', $body);
        $this->assertArrayNotHasKey('cover_path', $response->json('data'));
        $this->assertArrayNotHasKey('og_image_path', $response->json('data'));

        $hidden = $this->published($category, ['slug' => 'will-be-hidden']);
        $hidden->update(['status' => 'archived']);
        $this->getJson('/api/v1/news/will-be-hidden')->assertNotFound();
        $this->getJson('/api/v1/news/does-not-exist')->assertNotFound();
    }

    public function test_slug_is_generated_unique_and_can_be_manually_changed(): void
    {
        $category = $this->category();
        $one = $this->article($category, ['title' => 'A Useful Story', 'slug' => null]);
        $two = $this->article($category, ['title' => 'A Useful Story', 'slug' => null]);

        $this->assertSame('a-useful-story', $one->slug);
        $this->assertSame('a-useful-story-2', $two->slug);

        $one->update(['slug' => 'editorial-slug']);
        $this->assertSame('editorial-slug', $one->fresh()->slug);
        $this->expectException(ValidationException::class);
        $two->update(['slug' => 'editorial-slug']);
    }

    public function test_publishing_requires_complete_content_and_an_active_category(): void
    {
        $category = $this->category();
        $draft = $this->article($category, ['title' => 'Incomplete draft']);
        $this->assertSame('draft', $draft->status);

        try {
            $draft->update(['status' => 'published']);
            $this->fail('An incomplete article must not be published.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('excerpt', $exception->errors());
            $this->assertArrayHasKey('body', $exception->errors());
        }

        $category->update(['is_active' => false]);
        $draft = $this->article($category, $this->validArticleAttributes(['slug' => 'inactive-category-draft', 'status' => 'draft']));
        try {
            $draft->update(['status' => 'published']);
            $this->fail('An article cannot be published in an inactive category.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('category_id', $exception->errors());
        }
        $this->assertDatabaseHas('news_articles', ['slug' => 'inactive-category-draft', 'status' => 'draft']);
    }

    public function test_rich_text_is_sanitized_when_saved_and_api_sanitization_is_idempotent(): void
    {
        $category = $this->category();
        $article = $this->published($category, [
            'body' => '<p onclick="alert(1)">A &amp; B <em>emphasis</em> <a href="data:text/html,bad">bad link</a></p><script>alert(1)</script>',
        ]);

        $this->assertStringContainsString('<p>A &amp; B <em>emphasis</em>', $article->body);
        $this->assertStringNotContainsString('onclick', $article->body);
        $this->assertStringNotContainsString('<script', $article->body);
        $this->assertStringNotContainsString('data:text/html', $article->body);

        $responseBody = $this->getJson('/api/v1/news/'.$article->slug)->assertOk()->json('data.body');
        $this->assertStringContainsString('A &amp; B', $responseBody);
        $this->assertStringNotContainsString('&amp;amp;', $responseBody);
    }

    private function category(array $attributes = []): NewsCategory
    {
        return NewsCategory::query()->create(array_merge([
            'name' => 'Projects & Collaborations',
            'slug' => 'projects-collaborations',
            'sort_order' => 0,
            'is_active' => true,
        ], $attributes));
    }

    private function article(NewsCategory $category, array $attributes = []): NewsArticle
    {
        return NewsArticle::query()->create(array_merge([
            'category_id' => $category->id,
            'title' => 'Example News Article',
            'slug' => 'example-news-article',
            'status' => 'draft',
        ], $attributes));
    }

    private function published(NewsCategory $category, array $overrides = []): NewsArticle
    {
        $attributes = array_merge($this->validArticleAttributes(), $overrides);
        $path = 'news/'.$attributes['slug'].'.webp';
        Storage::disk('public')->put($path, 'image');
        $attributes['cover_path'] = array_key_exists('cover_path', $overrides) ? $overrides['cover_path'] : $path;

        return $this->article($category, $attributes);
    }

    private function validArticleAttributes(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Example News Article',
            'slug' => 'example-news-article',
            'excerpt' => 'A short factual summary.',
            'body' => '<p>Article body with <strong>formatting</strong>.</p>',
            'cover_path' => 'news/example-news-article.webp',
            'cover_alt' => 'Decorative planters in an interior',
            'published_at' => '2025-10-30',
            'status' => 'published',
            'seo_title' => 'Example News Article',
            'seo_description' => 'A factual search description.',
        ], $overrides);
    }
}
