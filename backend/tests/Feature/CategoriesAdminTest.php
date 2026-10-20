<?php

namespace Tests\Feature;

use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Models\Category;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoriesAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_edit_category_content_and_seo_while_slug_and_public_id_stay_stable(): void
    {
        $this->seed();
        $category = Category::query()->where('public_id', 'outdoor')->firstOrFail();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs(User::factory()->create(['is_admin' => true]))
            ->test(EditCategory::class, ['record' => $category->getKey()])
            ->assertSee('/shop/outdoor-pots')
            ->set('data.name', 'Updated Outdoor Pots')
            ->set('data.heading', 'Updated category heading')
            ->set('data.seo_title', 'Updated category SEO title')
            ->set('data.seo_description', 'Updated category SEO description')
            ->set('data.slug', 'changed-slug')
            ->set('data.public_id', 'changed-id')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('categories', [
            'id' => $category->getKey(),
            'public_id' => 'outdoor',
            'slug' => 'outdoor-pots',
            'name' => 'Updated Outdoor Pots',
            'seo_title' => 'Updated category SEO title',
        ]);
        $this->assertFalse(CategoryResource::canDelete($category->refresh()));
    }
}
