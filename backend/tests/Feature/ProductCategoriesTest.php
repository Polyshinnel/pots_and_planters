<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProductCategoryManager;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCategoriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_draft_without_categories_is_allowed_and_multiple_categories_round_trip(): void
    {
        [$brand, $supplier, $type] = $this->references();
        $manager = app(ProductCategoryManager::class);
        $draft = $manager->save($this->attributes($brand, $supplier, $type));
        $this->assertSame('draft', $draft->status);
        $this->assertCount(0, $draft->categories);
        $this->assertNull($draft->primaryCategory);

        $first = $this->category('planters');
        $second = $this->category('fibreglass');
        $saved = $manager->save($this->attributes($brand, $supplier, $type, [
            'sku' => 'SKU-2', 'category_ids' => [$first->id, $second->id], 'primary_category_id' => $second->id,
        ]));
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $saved->fresh()->categories()->pluck('categories.id')->all());
        $this->assertSame($second->id, $saved->fresh()->primaryCategory->id);
        $this->assertEquals($saved->id, $second->products()->first()->id);
    }

    public function test_database_enforces_unique_membership_primary_membership_and_protects_primary_detach(): void
    {
        [$brand, $supplier, $type] = $this->references();
        $first = $this->category('planters');
        $other = $this->category('fibreglass');
        $product = app(ProductCategoryManager::class)->save($this->attributes($brand, $supplier, $type, [
            'category_ids' => [$first->id, $other->id], 'primary_category_id' => $first->id,
        ]));

        try {
            $product->categories()->attach($first->id);
            $this->fail('Duplicate product/category membership must fail in MariaDB.');
        } catch (QueryException) {
            $this->assertDatabaseCount('product_category', 2);
        }

        try {
            $product->categories()->detach($first->id);
            $this->fail('The primary membership cannot be detached before clearing or changing primary.');
        } catch (QueryException) {
            $this->assertDatabaseHas('product_category', ['product_id' => $product->id, 'category_id' => $first->id]);
        }

        try {
            Product::query()->whereKey($product->id)->update(['primary_category_id' => 999999]);
            $this->fail('A primary category outside the assigned set must fail in MariaDB.');
        } catch (QueryException) {
            $this->assertSame($first->id, $product->fresh()->primary_category_id);
        }
    }

    public function test_application_rejects_duplicates_missing_ids_and_primary_outside_membership(): void
    {
        [$brand, $supplier, $type] = $this->references();
        $category = $this->category('planters');

        foreach ([
            ['category_ids' => [$category->id, $category->id], 'primary_category_id' => $category->id],
            ['category_ids' => [999999], 'primary_category_id' => 999999],
            ['category_ids' => [], 'primary_category_id' => $category->id],
        ] as $overrides) {
            try {
                app(ProductCategoryManager::class)->save($this->attributes($brand, $supplier, $type, $overrides));
                $this->fail('Invalid category selection must be rejected.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('products', 0);
            }
        }
    }

    public function test_primary_can_change_and_secondary_category_can_be_removed_atomically(): void
    {
        [$brand, $supplier, $type] = $this->references();
        $first = $this->category('planters');
        $second = $this->category('fibreglass');
        $manager = app(ProductCategoryManager::class);
        $product = $manager->save($this->attributes($brand, $supplier, $type, [
            'category_ids' => [$first->id, $second->id], 'primary_category_id' => $first->id,
        ]));

        $updated = $manager->save(array_merge($this->attributes($brand, $supplier, $type), [
            'category_ids' => [$second->id], 'primary_category_id' => $second->id,
        ]), $product);
        $this->assertSame([$second->id], $updated->categories->pluck('id')->all());
        $this->assertSame($second->id, $updated->primaryCategory->id);
    }

    public function test_published_product_requires_primary_and_filament_preserves_existing_inactive_categories(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        [$brand, $supplier, $type] = $this->references();
        $category = $this->category('planters');
        Livewire::actingAs($admin)->test(CreateProduct::class)
            ->set('data.brand_id', $brand->id)->set('data.supplier_id', $supplier->id)->set('data.product_type_id', $type->id)
            ->set('data.sku', 'P-1')->set('data.name', 'Pot')->set('data.status', 'published')
            ->call('create')->assertHasFormErrors();
        $this->assertDatabaseCount('products', 0);

        Livewire::actingAs($admin)->test(CreateProduct::class)
            ->set('data.brand_id', $brand->id)->set('data.supplier_id', $supplier->id)->set('data.product_type_id', $type->id)
            ->set('data.sku', 'P-1')->set('data.name', 'Pot')->set('data.category_ids', [$category->id])
            ->set('data.primary_category_id', $category->id)->set('data.status', 'published')
            ->call('create')->assertHasNoFormErrors();
        $product = Product::query()->firstOrFail();

        $category->update(['status' => 'archived']);
        Livewire::actingAs($admin)->test(EditProduct::class, ['record' => $product->id])
            ->assertFormSet(['category_ids' => [$category->id], 'primary_category_id' => $category->id])
            ->set('data.name', 'Pot Updated')->call('save')->assertHasNoFormErrors();
        $this->assertSame($category->id, $product->fresh()->primary_category_id);

        try {
            app(ProductCategoryManager::class)->save(array_merge($this->attributes($brand, $supplier, $type), [
                'status' => 'draft', 'category_ids' => [], 'primary_category_id' => $category->id,
            ]), $product);
            $this->fail('A primary category excluded from the selected set must be explicitly cleared or replaced.');
        } catch (ValidationException) {
            $this->assertSame($category->id, $product->fresh()->primary_category_id);
        }
    }

    public function test_non_admin_cannot_manage_product_categories(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/admin/products')->assertForbidden();
    }

    private function references(): array
    {
        return [Brand::query()->create(['name' => 'Brand']), Supplier::query()->create(['name' => 'Supplier']), ProductType::query()->create(['name' => 'Planter'])];
    }

    private function category(string $slug): Category
    {
        return Category::query()->create([
            'public_id' => $slug, 'slug' => $slug, 'name' => ucfirst($slug), 'heading' => ucfirst($slug),
            'description' => 'Description', 'tile_description' => 'Tile', 'seo_title' => ucfirst($slug),
            'seo_description' => 'Description', 'status' => 'published',
        ]);
    }

    private function attributes(Brand $brand, Supplier $supplier, ProductType $type, array $overrides = []): array
    {
        return array_merge([
            'brand_id' => $brand->id, 'supplier_id' => $supplier->id, 'product_type_id' => $type->id,
            'sku' => 'SKU-'.Str::random(6), 'name' => 'Product', 'category_ids' => [], 'primary_category_id' => null,
        ], $overrides);
    }
}
