<?php

namespace Tests\Feature;

use App\Filament\Resources\CatalogueResource\Pages\CreateCatalogue;
use App\Filament\Resources\CatalogueResource\Pages\EditCatalogue;
use App\Filament\Resources\CatalogueResource\Pages\ListCatalogues;
use App\Models\Catalogue;
use App\Models\Category;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogueResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_can_create_edit_archive_search_filter_and_delete_catalogues(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $categoryId = Category::query()->value('id');

        Livewire::actingAs($admin)->test(CreateCatalogue::class)
            ->set('data.title', 'Spring Collection')
            ->set('data.category_ids', [$categoryId])
            ->set('data.status', 'draft')
            ->set('data.sort_order', 20)
            ->call('create')
            ->assertHasNoFormErrors();
        $catalogue = Catalogue::query()->where('slug', 'spring-collection')->firstOrFail();
        $this->assertSame([$categoryId], $catalogue->categories()->pluck('categories.id')->all());

        Livewire::actingAs($admin)->test(EditCatalogue::class, ['record' => $catalogue->getKey()])
            ->set('data.title', 'Spring Collection Updated')
            ->set('data.status', 'archived')
            ->set('data.sort_order', 4)
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertDatabaseHas('catalogues', [
            'id' => $catalogue->getKey(), 'title' => 'Spring Collection Updated', 'status' => 'archived', 'sort_order' => 4,
        ]);

        Livewire::actingAs($admin)->test(ListCatalogues::class)
            ->searchTable('Spring Collection Updated')
            ->assertCanSeeTableRecords([$catalogue])
            ->filterTable('status', 'archived')
            ->assertCanSeeTableRecords([$catalogue]);

        Livewire::actingAs($admin)->test(EditCatalogue::class, ['record' => $catalogue->getKey()])
            ->callAction('delete')
            ->assertHasNoActionErrors();
        $this->assertDatabaseMissing('catalogues', ['id' => $catalogue->getKey()]);
    }

    public function test_filament_cannot_publish_without_files_or_categories(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)->test(CreateCatalogue::class)
            ->set('data.title', 'Invalid Publication')
            ->set('data.slug', 'invalid-publication')
            ->set('data.category_ids', [])
            ->set('data.status', 'published')
            ->set('data.sort_order', 0)
            ->call('create')
            ->assertHasFormErrors();

        $this->assertDatabaseMissing('catalogues', ['slug' => 'invalid-publication']);
    }
}
