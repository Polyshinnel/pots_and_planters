<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSeries;
use App\Models\ProductType;
use App\Models\Supplier;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ProductAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_product_defaults_to_draft_normalizes_sku_and_keeps_public_id_immutable(): void
    {
        [$brand, $supplier, $type] = $this->references();
        $product = Product::query()->create($this->productData($brand, $supplier, $type, ['sku' => '  ab-c  12! ', 'name' => '  Planter  ']));

        $this->assertSame('AB-C  12!', $product->sku);
        $this->assertSame('Planter', $product->name);
        $this->assertSame('draft', $product->status);
        $this->assertNull($product->price_aed);
        $this->assertSame($brand->id, $product->brand->id);
        $this->assertSame($supplier->id, $product->supplier->id);
        $this->assertSame($type->id, $product->productType->id);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $product->public_id);

        $originalId = $product->public_id;
        $product->public_id = (string) Str::uuid();
        try {
            $product->save();
            $this->fail('The public identifier must be immutable.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('public_id', $exception->errors());
        }

        $this->assertSame($originalId, $product->fresh()->public_id);
    }

    public function test_product_requires_valid_relations_sku_and_nonblank_name(): void
    {
        [$brand, $supplier, $type] = $this->references();
        $data = $this->productData($brand, $supplier, $type);

        foreach ([
            ['brand_id' => null, 'field' => 'brand_id'],
            ['supplier_id' => null, 'field' => 'supplier_id'],
            ['product_type_id' => null, 'field' => 'product_type_id'],
            ['sku' => '  ', 'field' => 'sku'],
            ['name' => " \t ", 'field' => 'name'],
            ['name' => str_repeat('x', 256), 'field' => 'name'],
        ] as $invalid) {
            $candidate = array_merge($data, array_diff_key($invalid, ['field' => true]));
            try {
                Product::query()->create($candidate);
                $this->fail('Invalid product data must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($invalid['field'], $exception->errors());
            }
        }
    }

    public function test_sku_is_unique_per_brand_and_same_sku_is_allowed_for_another_brand(): void
    {
        [$brand, $supplier, $type] = $this->references();
        $otherBrand = Brand::query()->create(['name' => 'Brand Two']);
        Product::query()->create($this->productData($brand, $supplier, $type, ['sku' => 'same-01']));

        try {
            Product::query()->create($this->productData($brand, $supplier, $type, ['sku' => ' SAME-01 ']));
            $this->fail('A duplicate Brand and SKU pair must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('sku', $exception->errors());
        }

        Product::query()->create($this->productData($otherBrand, $supplier, $type, ['sku' => 'same-01']));
        $this->assertSame(2, Product::query()->count());
    }

    public function test_price_may_be_null_or_positive_decimal_but_not_zero_or_negative(): void
    {
        [$brand, $supplier, $type] = $this->references();
        foreach ([null, '12.3400'] as $price) {
            $product = Product::query()->create($this->productData($brand, $supplier, $type, ['sku' => 'sku-'.(string) $price, 'price_aed' => $price]));
            $this->assertSame($price, $product->price_aed);
        }

        foreach (['0', '-0.01', '1.12345', '100000000000'] as $index => $price) {
            try {
                Product::query()->create($this->productData($brand, $supplier, $type, ['sku' => 'bad-'.$index, 'price_aed' => $price]));
                $this->fail('Invalid AED prices must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('price_aed', $exception->errors());
            }
        }

        $this->expectException(QueryException::class);
        Product::query()->insert(array_merge($this->productData($brand, $supplier, $type, ['sku' => 'DB-ZERO', 'price_aed' => '0']), [
            'public_id' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    public function test_series_must_belong_to_brand_and_database_enforces_composite_foreign_key(): void
    {
        [$brand, $supplier, $type] = $this->references();
        $otherBrand = Brand::query()->create(['name' => 'Other Brand']);
        $series = ProductSeries::query()->create(['brand_id' => $otherBrand->id, 'name' => 'Foreign Series']);

        try {
            Product::query()->create($this->productData($brand, $supplier, $type, ['series_id' => $series->id]));
            $this->fail('A series from another Brand must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('series_id', $exception->errors());
        }

        $this->expectException(QueryException::class);
        Product::query()->insert(array_merge($this->productData($brand, $supplier, $type, ['series_id' => $series->id]), [
            'public_id' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    public function test_referenced_catalogue_records_are_restricted_from_deletion(): void
    {
        [$brand, $supplier, $type] = $this->references();
        Product::query()->create($this->productData($brand, $supplier, $type));

        foreach ([$brand, $supplier, $type] as $reference) {
            try {
                $reference->delete();
                $this->fail('Product references must restrict deletion.');
            } catch (QueryException) {
                $this->assertModelExists($reference);
            }
        }
    }

    public function test_admin_can_create_edit_search_and_filter_products_without_hard_delete(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        [$brand, $supplier, $type] = $this->references();
        $series = ProductSeries::query()->create(['brand_id' => $brand->id, 'name' => 'Archived Series', 'is_active' => false]);
        $category = $this->category('planters');

        Livewire::actingAs($admin)->test(CreateProduct::class)
            ->set('data.brand_id', $brand->id)
            ->set('data.supplier_id', $supplier->id)
            ->set('data.product_type_id', $type->id)
            ->set('data.series_id', $series->id)
            ->set('data.sku', '  pp-100  ')
            ->set('data.name', '  Tall Pot  ')
            ->set('data.price_aed', '123.4500')
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->firstOrFail();
        $this->assertSame('PP-100', $product->sku);
        $this->assertSame('Tall Pot', $product->name);
        $this->assertSame($series->id, $product->series_id);
        $this->assertSame('123.4500', $product->price_aed);

        Livewire::actingAs($admin)->test(EditProduct::class, ['record' => $product->getKey()])
            ->assertFormSet(['series_id' => $series->id])
            ->set('data.name', 'Tall Pot Updated')
            ->set('data.category_ids', [$category->id])
            ->set('data.primary_category_id', $category->id)
            ->set('data.status', 'published')
            ->call('save')
            ->assertHasNoFormErrors();

        $product->refresh();
        $this->assertSame('Tall Pot Updated', $product->name);
        $this->assertSame('published', $product->status);
        $this->assertSame($series->id, $product->series_id);

        Livewire::actingAs($admin)->test(ListProducts::class)
            ->searchTable('Tall Pot Updated')->assertCanSeeTableRecords([$product]);
        Livewire::actingAs($admin)->test(ListProducts::class)
            ->filterTable('brand_id', $brand->id)->assertCanSeeTableRecords([$product]);
        Livewire::actingAs($admin)->test(ListProducts::class)
            ->filterTable('supplier_id', $supplier->id)->assertCanSeeTableRecords([$product]);
        Livewire::actingAs($admin)->test(ListProducts::class)
            ->filterTable('product_type_id', $type->id)->assertCanSeeTableRecords([$product]);
        Livewire::actingAs($admin)->test(ListProducts::class)
            ->filterTable('status', 'published')->assertCanSeeTableRecords([$product]);

        $otherBrand = Brand::query()->create(['name' => 'Brand Two']);
        Livewire::actingAs($admin)->test(EditProduct::class, ['record' => $product->getKey()])
            ->set('data.brand_id', $otherBrand->id)
            ->call('save')
            ->assertHasFormErrors(['series_id']);
        $this->assertSame($brand->id, $product->fresh()->brand_id);

        Livewire::actingAs($admin)->test(EditProduct::class, ['record' => $product->getKey()])
            ->set('data.brand_id', $otherBrand->id)
            ->set('data.series_id', null)
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame($otherBrand->id, $product->fresh()->brand_id);
        $this->assertNull($product->fresh()->series_id);

        $this->assertFalse(ProductResource::canDelete($product));
        $this->assertFalse(ProductResource::canDeleteAny());
    }

    public function test_non_admin_cannot_access_products_and_no_public_product_api_route_exists(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/admin/products')->assertForbidden();

        $productRoutes = collect(app('router')->getRoutes())->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/') && str_contains($route->uri(), 'products'));
        $this->assertSame(['GET', 'HEAD'], $productRoutes->firstWhere('uri', 'api/v1/products')?->methods());
        $this->assertSame(['GET', 'HEAD'], $productRoutes->firstWhere('uri', 'api/v1/products/{slug}')?->methods());
    }

    public function test_duplicate_sku_and_wrong_series_return_form_errors(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        [$brand, $supplier, $type] = $this->references();
        $otherBrand = Brand::query()->create(['name' => 'Other Brand']);
        $series = ProductSeries::query()->create(['brand_id' => $otherBrand->id, 'name' => 'Foreign Series']);
        Product::query()->create($this->productData($brand, $supplier, $type, ['sku' => 'EXISTING']));

        Livewire::actingAs($admin)->test(CreateProduct::class)
            ->set('data.brand_id', $brand->id)->set('data.supplier_id', $supplier->id)->set('data.product_type_id', $type->id)
            ->set('data.sku', ' existing ')->set('data.name', 'Duplicate')->call('create')
            ->assertHasFormErrors();
        $this->assertSame(1, Product::query()->count());

        Livewire::actingAs($admin)->test(CreateProduct::class)
            ->set('data.brand_id', $brand->id)->set('data.supplier_id', $supplier->id)->set('data.product_type_id', $type->id)
            ->set('data.series_id', $series->id)->set('data.sku', 'NEW')->set('data.name', 'Wrong Series')->call('create')
            ->assertHasFormErrors(['series_id']);
    }

    private function references(): array
    {
        return [
            Brand::query()->create(['name' => 'Brand One']),
            Supplier::query()->create(['name' => 'Supplier One']),
            ProductType::query()->create(['name' => 'Planter']),
        ];
    }

    private function productData(Brand $brand, Supplier $supplier, ProductType $type, array $overrides = []): array
    {
        return array_merge([
            'brand_id' => $brand->id,
            'supplier_id' => $supplier->id,
            'product_type_id' => $type->id,
            'sku' => 'SKU-100',
            'name' => 'Product One',
        ], $overrides);
    }

    private function category(string $slug): Category
    {
        return Category::query()->create([
            'public_id' => $slug, 'slug' => $slug, 'name' => ucfirst($slug), 'heading' => ucfirst($slug),
            'description' => 'Description', 'tile_description' => 'Tile', 'seo_title' => ucfirst($slug),
            'seo_description' => 'Description', 'status' => 'published',
        ]);
    }
}
