<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductSeriesResource;
use App\Filament\Resources\ProductSeriesResource\Pages\CreateProductSeries;
use App\Filament\Resources\ProductSeriesResource\Pages\EditProductSeries;
use App\Filament\Resources\ProductSeriesResource\Pages\ListProductSeries;
use App\Models\Brand;
use App\Models\ProductSeries;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ProductSeriesAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_series_requires_a_brand_and_generates_an_immutable_public_id(): void
    {
        $brand = Brand::query()->create(['name' => 'Brand One']);
        $series = ProductSeries::query()->create(['brand_id' => $brand->id, 'name' => '  Outdoor  ']);

        $this->assertSame('Outdoor', $series->name);
        $this->assertSame($brand->id, $series->brand->id);
        $this->assertTrue($series->is_active);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $series->public_id);
        $this->assertSame($series->public_id, $brand->productSeries()->firstOrFail()->public_id);
        $this->assertNotNull($series->created_at);
        $this->assertNotNull($series->updated_at);

        try {
            ProductSeries::query()->create(['name' => 'Missing Brand']);
            $this->fail('A brand is required.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('brand_id', $exception->errors());
        }

        try {
            ProductSeries::query()->create(['brand_id' => 999999, 'name' => 'Unknown Brand']);
            $this->fail('An existing brand is required.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('brand_id', $exception->errors());
        }

        $series->public_id = (string) Str::uuid();
        $this->expectException(ValidationException::class);
        $series->save();
    }

    public function test_series_name_is_trimmed_and_must_be_nonblank_and_within_limit(): void
    {
        $brand = Brand::query()->create(['name' => 'Brand One']);

        foreach ([" \t ", str_repeat('x', 256)] as $name) {
            try {
                ProductSeries::query()->create(['brand_id' => $brand->id, 'name' => $name]);
                $this->fail('Invalid names must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('name', $exception->errors());
            }
        }
    }

    public function test_brand_deletion_is_restricted_by_database_foreign_key(): void
    {
        $brand = Brand::query()->create(['name' => 'Brand One']);
        ProductSeries::query()->create(['brand_id' => $brand->id, 'name' => 'Outdoor']);

        try {
            $brand->delete();
            $this->fail('A referenced brand must not be deleted.');
        } catch (QueryException) {
            $this->assertDatabaseHas('brands', ['id' => $brand->id]);
            $this->assertDatabaseHas('product_series', ['brand_id' => $brand->id]);
        }
    }

    public function test_admin_can_manage_series_and_keep_an_inactive_brand_selected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $brand = Brand::query()->create(['name' => 'Active Brand']);

        Livewire::actingAs($admin)->test(CreateProductSeries::class)
            ->set('data.brand_id', $brand->id)
            ->set('data.name', '  New Series  ')
            ->call('create')
            ->assertHasNoFormErrors();
        $series = ProductSeries::query()->where('name', 'New Series')->firstOrFail();
        $originalPublicId = $series->public_id;
        $brand->update(['is_active' => false]);

        Livewire::actingAs($admin)->test(EditProductSeries::class, ['record' => $series->getKey()])
            ->assertFormSet(['brand_id' => $brand->id])
            ->set('data.name', 'Updated Series')
            ->set('data.is_active', false)
            ->call('save')
            ->assertHasNoFormErrors();

        $series->refresh();
        $this->assertSame('Updated Series', $series->name);
        $this->assertSame($brand->id, $series->brand_id);
        $this->assertSame($originalPublicId, $series->public_id);
        $this->assertFalse($series->is_active);
        $this->assertDatabaseHas('product_series', ['id' => $series->id, 'is_active' => false]);

        Livewire::actingAs($admin)->test(EditProductSeries::class, ['record' => $series->getKey()])
            ->assertFormSet(['brand_id' => $brand->id]);
        Livewire::actingAs($admin)->test(ListProductSeries::class)
            ->searchTable('Updated Series')
            ->assertCanSeeTableRecords([$series]);
        Livewire::actingAs($admin)->test(ListProductSeries::class)
            ->filterTable('brand_id', $brand->id)
            ->assertCanSeeTableRecords([$series]);
        Livewire::actingAs($admin)->test(ListProductSeries::class)
            ->filterTable('is_active', false)
            ->assertCanSeeTableRecords([$series]);

        $this->assertFalse(ProductSeriesResource::canDelete($series));
        $this->assertFalse(ProductSeriesResource::canDeleteAny());
    }

    public function test_non_admin_cannot_access_product_series_resource(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/admin/product-series')->assertForbidden();
    }
}
