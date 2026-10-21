<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Services\CatalogueManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CataloguesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed();
    }

    public function test_list_returns_only_published_catalogues_in_stable_order_and_safe_dto(): void
    {
        $categories = Category::query()->orderBy('id')->get();
        $first = $this->published('first-catalogue', 10, [$categories[0], $categories[1]]);
        $second = $this->published('second-catalogue', 10, [$categories[0]]);
        $this->published('last-catalogue', 20, [$categories[2]]);
        $this->draft('draft-catalogue', [$categories[0]]);
        $archived = $this->published('archived-catalogue', 30, [$categories[0]]);
        $archived->status = 'archived';
        $archived->save();

        $response = $this->getJson('/api/v1/catalogues')->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $first->public_id)
            ->assertJsonPath('data.1.id', $second->public_id)
            ->assertJsonPath('data.0.title', 'First Catalogue')
            ->assertJsonPath('data.0.slug', 'first-catalogue')
            ->assertJsonPath('data.0.description', null)
            ->assertJsonPath('data.0.coverImage', '/storage/covers/first-catalogue.webp')
            ->assertJsonPath('data.0.coverAlt', 'Cover for First Catalogue')
            ->assertJsonPath('data.0.categoryIds.0', $categories[0]->public_id)
            ->assertJsonPath('data.0.categoryIds.1', $categories[1]->public_id)
            ->assertJsonPath('data.0.categories.0.slug', $categories[0]->slug);

        $serialized = $response->getContent();
        $this->assertStringNotContainsString('pdf_path', $serialized);
        $this->assertStringNotContainsString('pdf_url', $serialized);
        $this->assertStringNotContainsString('catalogues/private-', $serialized);
        $this->assertArrayNotHasKey('cover_path', $response->json('data.0'));
    }

    public function test_category_slug_filter_matches_published_category_and_does_not_duplicate_catalogue(): void
    {
        $categories = Category::query()->orderBy('id')->get();
        $multiCategory = $this->published('multi-category', 0, [$categories[0], $categories[1]]);
        $this->published('other-category', 1, [$categories[1]]);

        $this->getJson('/api/v1/catalogues?category='.$categories[0]->slug)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $multiCategory->public_id);
        $this->getJson('/api/v1/catalogues?category=missing-slug')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_filter_ignores_unpublished_categories_and_unpublished_categories_are_omitted_from_dto(): void
    {
        $categories = Category::query()->orderBy('id')->get();
        $catalogue = $this->published('visible-category', 0, [$categories[0], $categories[1]]);
        $categories[0]->update(['status' => 'draft']);

        $this->getJson('/api/v1/catalogues?category='.$categories[0]->slug)->assertOk()->assertExactJson(['data' => []]);
        $response = $this->getJson('/api/v1/catalogues')->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame([$categories[1]->public_id], $response->json('data.0.categoryIds'));
        $this->assertSame($catalogue->public_id, $response->json('data.0.id'));
    }

    public function test_empty_results_and_invalid_category_parameters_have_predictable_responses(): void
    {
        $this->getJson('/api/v1/catalogues')->assertOk()->assertExactJson(['data' => []]);
        $this->getJson('/api/v1/catalogues?category[]=oops')->assertUnprocessable()->assertJsonValidationErrors('category');
        $this->getJson('/api/v1/catalogues?category=Bad%20Slug')->assertUnprocessable()->assertJsonValidationErrors('category');
    }

    public function test_category_eager_loading_uses_a_fixed_number_of_queries(): void
    {
        $categories = Category::query()->orderBy('id')->get();
        $this->published('one', 0, [$categories[0]]);
        $this->published('two', 1, [$categories[1]]);
        $this->published('three', 2, [$categories[2]]);

        $queryCount = 0;
        DB::listen(function (QueryExecuted $query) use (&$queryCount): void {
            if (str_contains($query->sql, 'catalogues') || str_contains($query->sql, 'catalogue_category')) {
                $queryCount++;
            }
        });

        $this->getJson('/api/v1/catalogues')->assertOk()->assertJsonCount(3, 'data');

        $this->assertSame(2, $queryCount, 'Catalogue list should load records and categories in a fixed two queries.');
    }

    /** @param list<Category> $categories */
    private function published(string $slug, int $sortOrder, array $categories)
    {
        $pdfPath = 'catalogues/private-'.$slug.'.pdf';
        $coverPath = 'covers/'.$slug.'.webp';
        Storage::disk('local')->put($pdfPath, '%PDF-test');
        Storage::disk('public')->put($coverPath, 'cover');

        $manager = app(CatalogueManager::class);
        $catalogue = $manager->save([
            'slug' => $slug,
            'title' => str($slug)->replace('-', ' ')->title()->toString(),
            'description' => null,
            'cover_alt' => 'Cover for '.str($slug)->replace('-', ' ')->title(),
            'sort_order' => $sortOrder,
        ], array_map(fn (Category $category): int => $category->getKey(), $categories));

        return $manager->save([
            'status' => 'published',
            'pdf_path' => $pdfPath,
            'cover_path' => $coverPath,
            'sort_order' => $sortOrder,
        ], array_map(fn (Category $category): int => $category->getKey(), $categories), $catalogue);
    }

    /** @param list<Category> $categories */
    private function draft(string $slug, array $categories): void
    {
        app(CatalogueManager::class)->save([
            'slug' => $slug,
            'title' => 'Draft',
        ], array_map(fn (Category $category): int => $category->getKey(), $categories));
    }
}
