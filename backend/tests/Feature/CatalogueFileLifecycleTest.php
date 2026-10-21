<?php

namespace Tests\Feature;

use App\Models\Catalogue;
use App\Models\Category;
use App\Services\CatalogueFileLifecycle;
use App\Services\CatalogueManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CatalogueFileLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed();
    }

    public function test_replacement_commits_new_paths_then_removes_old_files(): void
    {
        $categoryId = Category::query()->value('id');
        Storage::disk('local')->put('catalogues/old.pdf', '%PDF-old');
        Storage::disk('public')->put('catalogue-covers/old.jpg', 'old-cover');
        $catalogue = app(CatalogueManager::class)->save([
            'slug' => 'replace-me', 'title' => 'Replace me', 'status' => 'published',
            'pdf_path' => 'catalogues/old.pdf', 'cover_path' => 'catalogue-covers/old.jpg',
        ], [$categoryId]);

        Storage::disk('local')->put('catalogues/new.pdf', '%PDF-new');
        Storage::disk('public')->put('catalogue-covers/new.jpg', 'new-cover');
        $oldPaths = app(CatalogueFileLifecycle::class)->paths($catalogue);
        $saved = app(CatalogueManager::class)->save([
            'pdf_path' => 'catalogues/new.pdf', 'cover_path' => 'catalogue-covers/new.jpg',
        ], [$categoryId], $catalogue);
        app(CatalogueFileLifecycle::class)->clean([
            'pdf_path' => $oldPaths['pdf_path'], 'cover_path' => $oldPaths['cover_path'],
        ]);

        $this->assertSame('catalogues/new.pdf', $saved->pdf_path);
        Storage::disk('local')->assertMissing('catalogues/old.pdf');
        Storage::disk('public')->assertMissing('catalogue-covers/old.jpg');
        Storage::disk('local')->assertExists('catalogues/new.pdf');
        Storage::disk('public')->assertExists('catalogue-covers/new.jpg');
    }

    public function test_failed_save_keeps_old_files_and_cleans_only_unreferenced_new_files(): void
    {
        $categoryId = Category::query()->value('id');
        Storage::disk('local')->put('catalogues/old.pdf', '%PDF-old');
        Storage::disk('public')->put('catalogue-covers/old.jpg', 'old-cover');
        $catalogue = app(CatalogueManager::class)->save([
            'slug' => 'rollback-me', 'title' => 'Before', 'status' => 'published',
            'pdf_path' => 'catalogues/old.pdf', 'cover_path' => 'catalogue-covers/old.jpg',
        ], [$categoryId]);
        Storage::disk('local')->put('catalogues/new.pdf', '%PDF-new');
        Storage::disk('public')->put('catalogue-covers/new.jpg', 'new-cover');

        try {
            app(CatalogueManager::class)->save([
                'title' => 'After', 'pdf_path' => 'catalogues/new.pdf', 'cover_path' => 'catalogue-covers/new.jpg',
            ], [999999], $catalogue);
            $this->fail('The invalid category should abort the save.');
        } catch (ValidationException) {
            app(CatalogueFileLifecycle::class)->cleanupNew(
                ['pdf_path' => 'catalogues/old.pdf', 'cover_path' => 'catalogue-covers/old.jpg'],
                ['pdf_path' => 'catalogues/new.pdf', 'cover_path' => 'catalogue-covers/new.jpg'],
            );
        }

        $this->assertSame('Before', $catalogue->fresh()->title);
        Storage::disk('local')->assertExists('catalogues/old.pdf');
        Storage::disk('public')->assertExists('catalogue-covers/old.jpg');
        Storage::disk('local')->assertMissing('catalogues/new.pdf');
        Storage::disk('public')->assertMissing('catalogue-covers/new.jpg');
    }

    public function test_shared_files_are_kept_until_the_last_catalogue_reference_is_removed(): void
    {
        $categoryId = Category::query()->value('id');
        Storage::disk('local')->put('catalogues/shared.pdf', '%PDF-shared');
        Storage::disk('public')->put('catalogue-covers/shared.jpg', 'shared-cover');
        $attributes = [
            'status' => 'published', 'pdf_path' => 'catalogues/shared.pdf', 'cover_path' => 'catalogue-covers/shared.jpg',
        ];
        $first = app(CatalogueManager::class)->save(['slug' => 'shared-one', 'title' => 'One', ...$attributes], [$categoryId]);
        app(CatalogueManager::class)->save(['slug' => 'shared-two', 'title' => 'Two', ...$attributes], [$categoryId]);

        $paths = app(CatalogueFileLifecycle::class)->paths($first);
        $first->delete();
        app(CatalogueFileLifecycle::class)->clean($paths);
        Storage::disk('local')->assertExists('catalogues/shared.pdf');
        Storage::disk('public')->assertExists('catalogue-covers/shared.jpg');

        Catalogue::query()->where('slug', 'shared-two')->delete();
        app(CatalogueFileLifecycle::class)->clean($paths);
        Storage::disk('local')->assertMissing('catalogues/shared.pdf');
        Storage::disk('public')->assertMissing('catalogue-covers/shared.jpg');
    }

    public function test_filesystem_exception_during_post_commit_cleanup_is_logged_and_does_not_reverse_saved_operation(): void
    {
        $catalogue = app(CatalogueManager::class)->save(['slug' => 'cleanup-error', 'title' => 'Cleanup error'], []);
        $disk = \Mockery::mock();
        $disk->shouldReceive('exists')->once()->with('catalogues/old.pdf')->andReturn(true);
        $disk->shouldReceive('delete')->once()->with('catalogues/old.pdf')->andThrow(new \RuntimeException('Disk unavailable'));
        Storage::shouldReceive('disk')->once()->with('local')->andReturn($disk);
        Log::shouldReceive('warning')->once()->with('Could not remove unused catalogue file.', \Mockery::on(
            fn (array $context): bool => $context['disk'] === 'local'
                && $context['path'] === 'catalogues/old.pdf'
                && $context['message'] === 'Disk unavailable',
        ));

        app(CatalogueFileLifecycle::class)->clean(['pdf_path' => 'catalogues/old.pdf']);

        $this->assertDatabaseHas('catalogues', ['id' => $catalogue->getKey(), 'title' => 'Cleanup error']);
    }
}
