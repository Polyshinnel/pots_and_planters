<?php

namespace Tests\Feature;

use App\Models\Catalogue;
use App\Models\Category;
use App\Services\CatalogueManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CataloguesTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_schema_and_many_to_many_relationship_use_internal_ids(): void
    {
        $this->seed();
        $first = Category::query()->firstOrFail();
        $second = Category::query()->where('id', '!=', $first->getKey())->firstOrFail();
        $catalogue = app(CatalogueManager::class)->save([
            'slug' => 'summer-collection', 'title' => 'Summer Collection',
        ], [$first->getKey(), $second->getKey()]);

        $this->assertTrue(Schema::hasColumns('catalogues', ['public_id', 'slug', 'pdf_path', 'cover_path', 'status']));
        $this->assertSame([$first->getKey(), $second->getKey()], $catalogue->categories->modelKeys());
        $this->assertTrue($first->catalogues()->whereKey($catalogue->getKey())->exists());
        $this->assertSame('draft', $catalogue->status);
        $this->assertNotEmpty($catalogue->public_id);

        $catalogue->categories()->sync([$first->getKey(), $first->getKey()]);
        $this->assertSame(1, $catalogue->categories()->count());
    }

    public function test_public_id_is_stable_and_slug_and_public_id_are_unique(): void
    {
        $catalogue = app(CatalogueManager::class)->save(['slug' => 'first-catalogue', 'title' => 'First'], []);
        $publicId = $catalogue->public_id;
        app(CatalogueManager::class)->save(['title' => 'Updated'], [], $catalogue);
        $this->assertSame($publicId, $catalogue->refresh()->public_id);

        $catalogue->public_id = 'changed';
        $this->expectException(ValidationException::class);
        $catalogue->save();
    }

    public function test_draft_can_be_incomplete_and_published_requires_files_and_category(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seed();
        $manager = app(CatalogueManager::class);
        $draft = $manager->save(['slug' => 'incomplete', 'title' => 'Incomplete'], []);
        $this->assertSame('draft', $draft->status);

        $categoryId = Category::query()->value('id');
        try {
            $manager->save([
                'slug' => 'missing-files', 'title' => 'Missing files', 'status' => 'published',
                'pdf_path' => 'catalogues/missing.pdf', 'cover_path' => 'covers/missing.webp',
            ], [$categoryId]);
            $this->fail('Publishing paths without corresponding files must fail.');
        } catch (ValidationException) {
            $this->assertDatabaseMissing('catalogues', ['slug' => 'missing-files']);
        }

        Storage::disk('local')->put('catalogues/front.pdf', '%PDF-test');
        Storage::disk('public')->put('covers/front.webp', 'cover-test');
        foreach ([
            ['pdf_path' => null, 'cover_path' => 'covers/front.webp'],
            ['pdf_path' => 'catalogues/front.pdf', 'cover_path' => null],
        ] as $missingFile) {
            try {
                $manager->save([
                    'slug' => 'ready-catalogue', 'title' => 'Ready', 'status' => 'published',
                    ...$missingFile,
                ], [$categoryId]);
                $this->fail('Publishing without both files must fail.');
            } catch (ValidationException) {
                $this->assertDatabaseMissing('catalogues', ['slug' => 'ready-catalogue']);
            }
        }

        $this->expectException(ValidationException::class);
        $manager->save([
            'slug' => 'ready-catalogue', 'title' => 'Ready', 'status' => 'published',
            'pdf_path' => 'catalogues/front.pdf', 'cover_path' => 'covers/front.webp',
        ], []);
    }

    public function test_published_catalogue_cannot_become_incomplete_or_lose_last_category(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seed();
        $manager = app(CatalogueManager::class);
        $categoryId = Category::query()->value('id');
        Storage::disk('local')->put('catalogues/published.pdf', '%PDF-test');
        Storage::disk('public')->put('covers/published.webp', 'cover-test');
        $catalogue = $manager->save([
            'slug' => 'published-catalogue', 'title' => 'Published', 'status' => 'published',
            'pdf_path' => 'catalogues/published.pdf', 'cover_path' => 'covers/published.webp',
        ], [$categoryId]);

        try {
            $manager->save(['pdf_path' => null], [$categoryId], $catalogue);
            $this->fail('Removing the PDF from a published catalogue must fail.');
        } catch (ValidationException) {
            $this->assertSame('published', $catalogue->fresh()->status);
            $this->assertSame('catalogues/published.pdf', $catalogue->fresh()->pdf_path);
        }

        try {
            $manager->save([], [], $catalogue);
            $this->fail('Removing the last category from a published catalogue must fail.');
        } catch (ValidationException) {
            $this->assertSame(1, $catalogue->fresh()->categories()->count());
        }
    }

    public function test_catalogue_file_paths_must_be_relative_and_cannot_traverse_directories(): void
    {
        foreach (['/etc/private.pdf', '../private.pdf', 'catalogues/../../private.pdf'] as $path) {
            try {
                app(CatalogueManager::class)->save([
                    'slug' => 'unsafe-path', 'title' => 'Unsafe', 'pdf_path' => $path,
                ], []);
                $this->fail('Unsafe paths must be rejected.');
            } catch (ValidationException) {
                $this->assertDatabaseMissing('catalogues', ['slug' => 'unsafe-path']);
            }
        }
    }

    public function test_failed_category_sync_rolls_back_attributes_and_links(): void
    {
        $this->seed();
        $manager = app(CatalogueManager::class);
        $categoryId = Category::query()->value('id');
        $catalogue = $manager->save(['slug' => 'safe', 'title' => 'Before'], [$categoryId]);

        try {
            $manager->save(['title' => 'After'], [999999], $catalogue);
            $this->fail('Unknown categories must be rejected.');
        } catch (ValidationException) {
            $this->assertSame('Before', $catalogue->fresh()->title);
            $this->assertSame([$categoryId], $catalogue->fresh()->categories->modelKeys());
        }
    }

    public function test_exception_after_catalogue_write_rolls_back_attributes_and_pivot_links(): void
    {
        $this->seed();
        $manager = app(CatalogueManager::class);
        $categoryIds = Category::query()->orderBy('id')->limit(2)->pluck('id')->all();
        $catalogue = $manager->save(['slug' => 'rollback-case', 'title' => 'Before'], [$categoryIds[0]]);

        try {
            DB::transaction(function () use ($manager, $catalogue, $categoryIds): void {
                $manager->save(['title' => 'After'], [$categoryIds[1]], $catalogue);
                throw new \RuntimeException('Simulated failure after catalogue and pivot writes.');
            });
            $this->fail('The simulated failure must escape the transaction.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated failure after catalogue and pivot writes.', $exception->getMessage());
        }

        $catalogue->refresh();
        $this->assertSame('Before', $catalogue->title);
        $this->assertSame([$categoryIds[0]], $catalogue->categories()->orderBy('categories.id')->pluck('categories.id')->all());
    }

    public function test_category_cannot_be_deleted_when_it_is_the_only_category_of_a_published_catalogue(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seed();
        $category = Category::query()->firstOrFail();
        Storage::disk('local')->put('catalogues/protected.pdf', '%PDF-test');
        Storage::disk('public')->put('covers/protected.webp', 'cover-test');
        app(CatalogueManager::class)->save([
            'slug' => 'protected-catalogue', 'title' => 'Protected', 'status' => 'published',
            'pdf_path' => 'catalogues/protected.pdf', 'cover_path' => 'covers/protected.webp',
        ], [$category->getKey()]);

        try {
            $category->delete();
            $this->fail('Deleting the last category of a published catalogue must fail.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('categories', ['id' => $category->getKey()]);
            $this->assertDatabaseHas('catalogue_category', ['category_id' => $category->getKey()]);
        }
    }

    public function test_database_rejects_duplicate_slugs_and_cascades_pivot_on_catalogue_delete(): void
    {
        $this->seed();
        $categoryId = Category::query()->value('id');
        $catalogue = app(CatalogueManager::class)->save(['slug' => 'unique', 'title' => 'One'], [$categoryId]);
        try {
            Catalogue::query()->create(['slug' => 'unique', 'title' => 'Two']);
            $this->fail('The database must reject duplicate slugs.');
        } catch (QueryException) {
            $this->assertDatabaseHas('catalogues', ['id' => $catalogue->getKey(), 'slug' => 'unique']);
        }
        $catalogue->delete();
        $this->assertDatabaseMissing('catalogue_category', ['catalogue_id' => $catalogue->getKey()]);
    }
}
