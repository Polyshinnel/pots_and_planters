<?php

namespace Tests\Feature;

use App\Filament\Resources\PageSeoResource;
use App\Filament\Resources\PageSeoResource\Pages\EditPageSeo;
use App\Models\PageSeo;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PageSeoAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_page_seo_without_changing_its_fixed_key(): void
    {
        $this->seed();
        $page = PageSeo::query()->where('page_key', 'about')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)->test(EditPageSeo::class, ['record' => $page->getKey()])
            ->set('data.title', '<script>alert(1)</script>')
            ->set('data.description', 'About page test description')
            ->set('data.page_key', 'contacts')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->set('data.title', 'BE-02 SSR verification')
            ->assertSee('BE-02 SSR verification')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('page_seos', [
            'id' => $page->getKey(),
            'page_key' => 'about',
            'title' => 'BE-02 SSR verification',
        ]);
        $this->assertFalse(PageSeoResource::canCreate());
        $this->assertFalse(PageSeoResource::canDelete($page));
    }
}
