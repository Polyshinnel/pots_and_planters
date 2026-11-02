<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Supplier;
use App\Services\ProductCategoryManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductDescriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_description_column_is_nullable_and_long_text(): void
    {
        $this->assertTrue(Schema::hasColumn('products', 'description'));
        $this->assertSame('longtext', Schema::getColumnType('products', 'description'));
    }

    public function test_create_and_edit_keep_description_optional_for_draft_and_published_products(): void
    {
        [$brand, $supplier, $type] = $this->references();
        $draft = app(ProductCategoryManager::class)->save($this->data($brand, $supplier, $type));
        $this->assertNull($draft->description);

        $category = $this->category();
        $published = app(ProductCategoryManager::class)->save($this->data($brand, $supplier, $type, [
            'sku' => 'SKU-002', 'status' => 'published', 'category_ids' => [$category->id], 'primary_category_id' => $category->id,
        ]));
        $this->assertNull($published->description);

        $published = app(ProductCategoryManager::class)->save($this->data($brand, $supplier, $type, [
            'sku' => 'SKU-002', 'status' => 'published', 'category_ids' => [$category->id], 'primary_category_id' => $category->id,
            'description' => '<p>Edited <strong>description</strong></p>',
        ]), $published);
        $this->assertSame('<p>Edited <strong>description</strong></p>', $published->fresh()->description);
    }

    public function test_product_description_is_sanitized_on_model_writes_and_does_not_change_seo(): void
    {
        [$brand, $supplier, $type] = $this->references();
        $product = Product::query()->create($this->data($brand, $supplier, $type, [
            'description' => '<h2>Details</h2><p onclick="alert(1)">Safe <em>text</em> <a href="javascript:alert(1)">bad</a></p><script>alert(1)</script><iframe src="https://evil.test">bad frame</iframe>',
            'seo_description' => 'Independent SEO description',
        ]));

        $saved = $product->fresh();
        $this->assertStringContainsString('<h2>Details</h2>', $saved->description);
        $this->assertStringContainsString('<em>text</em>', $saved->description);
        $this->assertStringNotContainsString('<script', $saved->description);
        $this->assertStringNotContainsString('<iframe', $saved->description);
        $this->assertStringNotContainsString('onclick', $saved->description);
        $this->assertStringNotContainsString('javascript:', $saved->description);
        $this->assertSame('Independent SEO description', $saved->seo_description);

        $product->description = '<script>alert(1)</script>';
        $product->save();
        $this->assertNull($product->fresh()->description);

        $product->description = '  ';
        $product->save();
        $this->assertNull($product->fresh()->description);
        $this->assertSame('Independent SEO description', $product->fresh()->seo_description);
    }

    private function references(): array
    {
        return [
            Brand::query()->create(['name' => 'Description Brand']),
            Supplier::query()->create(['name' => 'Description Supplier']),
            ProductType::query()->create(['name' => 'Description Type']),
        ];
    }

    private function data(Brand $brand, Supplier $supplier, ProductType $type, array $overrides = []): array
    {
        return array_merge([
            'brand_id' => $brand->id, 'supplier_id' => $supplier->id, 'product_type_id' => $type->id,
            'sku' => 'SKU-001', 'name' => 'Description Product', 'category_ids' => [], 'primary_category_id' => null,
        ], $overrides);
    }

    private function category(): Category
    {
        return Category::query()->create([
            'public_id' => 'description-category', 'slug' => 'description-category', 'name' => 'Description',
            'heading' => 'Description', 'description' => 'Category details', 'tile_description' => 'Tile',
            'seo_title' => 'Description', 'seo_description' => 'Category SEO', 'status' => 'published',
        ]);
    }
}
