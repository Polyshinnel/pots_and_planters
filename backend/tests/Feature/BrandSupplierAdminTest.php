<?php

namespace Tests\Feature;

use App\Filament\Resources\BrandResource;
use App\Filament\Resources\BrandResource\Pages\CreateBrand;
use App\Filament\Resources\BrandResource\Pages\EditBrand;
use App\Filament\Resources\BrandResource\Pages\ListBrands;
use App\Filament\Resources\SupplierResource;
use App\Filament\Resources\SupplierResource\Pages\CreateSupplier;
use App\Filament\Resources\SupplierResource\Pages\EditSupplier;
use App\Filament\Resources\SupplierResource\Pages\ListSuppliers;
use App\Models\Brand;
use App\Models\Supplier;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class BrandSupplierAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_brand_and_supplier_are_independent_uuid_backed_directories(): void
    {
        $brand = Brand::query()->create(['name' => '  Brand One  ']);
        $anotherBrand = Brand::query()->create(['name' => 'Brand Two']);
        $supplier = Supplier::query()->create(['name' => '  Supplier One  ']);

        $this->assertSame('Brand One', $brand->name);
        $this->assertSame('Supplier One', $supplier->name);
        $this->assertTrue($brand->is_active);
        $this->assertTrue($supplier->is_active);
        $this->assertNotSame($brand->public_id, $anotherBrand->public_id);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $brand->public_id);
        $this->assertDatabaseHas('brands', ['id' => $brand->id, 'name' => 'Brand One', 'is_active' => true]);
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'Supplier One', 'is_active' => true]);

        foreach ([[$brand, 'brand'], [$supplier, 'supplier']] as [$record, $key]) {
            $record->public_id = (string) Str::uuid();

            try {
                $record->save();
                $this->fail("Changing {$key} public_id must be rejected.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('public_id', $exception->errors());
            }
        }
    }

    public function test_names_must_be_nonblank_and_fit_the_database_column(): void
    {
        foreach ([Brand::class, Supplier::class] as $model) {
            try {
                $model::query()->create(['name' => " \t "]);
                $this->fail('A whitespace-only name must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('name', $exception->errors());
            }

            try {
                $model::query()->create(['name' => str_repeat('x', 256)]);
                $this->fail('A name longer than 255 characters must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('name', $exception->errors());
            }
        }
    }

    public function test_admin_can_create_edit_search_filter_and_deactivate_both_directories(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $inactiveBrand = Brand::query()->create(['name' => 'Archived Brand', 'is_active' => false]);
        $inactiveSupplier = Supplier::query()->create(['name' => 'Archived Supplier', 'is_active' => false]);

        Livewire::actingAs($admin)->test(CreateBrand::class)
            ->set('data.name', '   ')
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);

        Livewire::actingAs($admin)->test(CreateBrand::class)
            ->set('data.name', '  New Brand  ')
            ->call('create')
            ->assertHasNoFormErrors();
        $brand = Brand::query()->where('name', 'New Brand')->firstOrFail();

        Livewire::actingAs($admin)->test(EditBrand::class, ['record' => $brand->getKey()])
            ->set('data.name', 'Updated Brand')
            ->set('data.is_active', false)
            ->call('save')
            ->assertHasNoFormErrors();
        $brand->refresh();
        $this->assertSame('Updated Brand', $brand->name);
        $this->assertFalse($brand->is_active);

        Livewire::actingAs($admin)->test(ListBrands::class)
            ->searchTable('Updated Brand')
            ->assertCanSeeTableRecords([$brand]);
        Livewire::actingAs($admin)->test(ListBrands::class)
            ->filterTable('is_active', false)
            ->assertCanSeeTableRecords([$brand, $inactiveBrand]);

        Livewire::actingAs($admin)->test(CreateSupplier::class)
            ->set('data.name', '  New Supplier  ')
            ->call('create')
            ->assertHasNoFormErrors();
        $supplier = Supplier::query()->where('name', 'New Supplier')->firstOrFail();

        Livewire::actingAs($admin)->test(EditSupplier::class, ['record' => $supplier->getKey()])
            ->set('data.name', 'Updated Supplier')
            ->set('data.is_active', false)
            ->call('save')
            ->assertHasNoFormErrors();
        $supplier->refresh();
        $this->assertSame('Updated Supplier', $supplier->name);
        $this->assertFalse($supplier->is_active);

        Livewire::actingAs($admin)->test(ListSuppliers::class)
            ->searchTable('Updated Supplier')
            ->assertCanSeeTableRecords([$supplier]);
        Livewire::actingAs($admin)->test(ListSuppliers::class)
            ->filterTable('is_active', false)
            ->assertCanSeeTableRecords([$supplier, $inactiveSupplier]);

        $this->assertFalse(BrandResource::canDelete($brand));
        $this->assertFalse(SupplierResource::canDelete($supplier));
    }

    public function test_non_admin_cannot_access_brand_or_supplier_resources(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/admin/brands')->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/admin/suppliers')->assertForbidden();
    }
}
