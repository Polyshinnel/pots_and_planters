<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoriesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_is_idempotent_and_keeps_product_ids_separate_from_slugs(): void
    {
        Storage::fake('public');
        $this->seed();
        $this->assertTrue(Storage::disk('public')->exists('categories/outdoor-pots.webp'));
        Category::query()->where('public_id', 'outdoor')->update(['name' => 'Editor name']);
        Storage::disk('public')->put('categories/outdoor-pots.webp', 'editor image');
        $this->seed();
        $this->assertDatabaseCount('categories', 3);
        $this->assertDatabaseHas('categories', ['public_id' => 'outdoor', 'slug' => 'outdoor-pots', 'name' => 'Editor name']);
        $this->assertSame('editor image', Storage::disk('public')->get('categories/outdoor-pots.webp'));
    }

    public function test_list_exposes_only_published_categories_in_deterministic_order_and_safe_dto(): void
    {
        $this->seed();
        Category::query()->where('public_id', 'indoor')->update(['status' => 'draft']);

        $response = $this->getJson('/api/v1/categories')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', 'outdoor')
            ->assertJsonPath('data.0.slug', 'outdoor-pots')
            ->assertJsonPath('data.0.path', '/shop/outdoor-pots')
            ->assertJsonPath('data.0.image', '/storage/categories/outdoor-pots.webp')
            ->assertJsonPath('data.0.imageAlt', 'Three textured outdoor planters with greenery on a timber terrace')
            ->assertJsonPath('data.1.id', 'artificial');
        $this->assertArrayNotHasKey('image_path', $response->json('data.0'));
    }

    public function test_show_returns_published_slug_and_hides_draft_and_unknown_categories(): void
    {
        $this->seed();
        $this->getJson('/api/v1/categories/outdoor-pots')->assertOk()->assertJsonPath('data.id', 'outdoor');
        Category::query()->where('public_id', 'indoor')->update(['status' => 'draft']);
        $this->getJson('/api/v1/categories/indoor-pots')->assertNotFound();
        $this->getJson('/api/v1/categories/missing')->assertNotFound();
    }

    public function test_repeat_seed_does_not_create_unrelated_categories(): void
    {
        $this->seed();
        Category::query()->create(['public_id'=>'legacy-extra','slug'=>'legacy-extra','name'=>'Extra','heading'=>'Extra','description'=>'Extra','tile_description'=>'Extra','seo_title'=>'Extra','seo_description'=>'Extra','status'=>'published']);
        $this->seed();
        $this->assertDatabaseCount('categories', 4);
    }
}
