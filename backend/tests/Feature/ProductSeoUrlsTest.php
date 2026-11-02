<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\ProductImage;
use App\Models\ProductType;
use App\Models\Supplier;
use App\Services\ProductCategoryManager;
use App\Services\ProductUrlResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductSeoUrlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_slug_defaults_are_global_unique_and_stable_when_name_changes(): void
    {
        [$brand, $supplier, $type] = $this->references();
        $first = app(ProductCategoryManager::class)->save($this->attributes($brand, $supplier, $type, ['name' => 'Tall Pot']));
        $second = app(ProductCategoryManager::class)->save($this->attributes($brand, $supplier, $type, ['name' => 'Tall Pot', 'sku' => 'SKU-2']));

        $this->assertSame('tall-pot', $first->slug);
        $this->assertSame('tall-pot-2', $second->slug);

        try {
            app(ProductCategoryManager::class)->save($this->attributes($brand, $supplier, $type, ['slug' => 'tall-pot', 'sku' => 'SKU-3']));
            $this->fail('An explicitly repeated product slug must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('slug', $exception->errors());
        }

        $changed = app(ProductCategoryManager::class)->save($this->attributes($brand, $supplier, $type, [
            'name' => 'Renamed Pot', 'sku' => $first->sku, 'slug' => 'editorial-pot',
        ]), $first);
        $changedAgain = app(ProductCategoryManager::class)->save($this->attributes($brand, $supplier, $type, [
            'name' => 'Another Name', 'sku' => $first->sku, 'slug' => 'editorial-pot',
        ]), $changed);

        $this->assertSame('editorial-pot', $changedAgain->slug);
        $this->assertSame('Another Name', $changedAgain->effective_seo_title);
    }

    public function test_manual_seo_overrides_are_sanitized_and_blank_values_use_safe_defaults(): void
    {
        [$brand, $supplier, $type] = $this->references();
        $product = app(ProductCategoryManager::class)->save($this->attributes($brand, $supplier, $type, [
            'seo_title' => '<b>Custom title</b>', 'seo_description' => '<script>alert(1)</script>Useful details',
        ]));

        $this->assertSame('Custom title', $product->seo_title);
        $this->assertSame('Useful details', $product->seo_description);
        $this->assertSame('Custom title', $product->effective_seo_title);

        $product = app(ProductCategoryManager::class)->save($this->attributes($brand, $supplier, $type, [
            'sku' => $product->sku, 'name' => 'Default Title', 'slug' => $product->slug, 'seo_title' => '', 'seo_description' => '',
        ]), $product);
        $this->assertNull($product->seo_title);
        $this->assertSame('Default Title', $product->effective_seo_title);
        $this->assertNull($product->effective_seo_description);
    }

    public function test_slug_and_primary_category_changes_keep_aliases_resolving_directly_to_current_canonical(): void
    {
        [$brand, $supplier, $type] = $this->references();
        $primary = $this->category('pots');
        $secondary = $this->category('fibreglass');
        $nextPrimary = $this->category('outdoor');
        $manager = app(ProductCategoryManager::class);
        $resolver = app(ProductUrlResolver::class);
        $product = $manager->save($this->attributes($brand, $supplier, $type, [
            'category_ids' => [$primary->id, $secondary->id], 'primary_category_id' => $primary->id, 'status' => 'published',
        ]));

        $old = '/shop/pots/product';
        $this->assertSame('canonical', $resolver->resolve($old)['type']);
        $this->assertSame('secondary', $resolver->resolve('/shop/fibreglass/product')['type']);

        $product = $manager->save($this->attributes($brand, $supplier, $type, [
            'sku' => $product->sku, 'name' => $product->name, 'slug' => 'new-product', 'category_ids' => [$secondary->id, $nextPrimary->id],
            'primary_category_id' => $nextPrimary->id, 'status' => 'published',
        ]), $product);

        $canonical = '/shop/outdoor/new-product';
        $this->assertSame('alias', $resolver->resolve($old)['type']);
        $this->assertSame($canonical, $resolver->resolve($old)['canonical_path']);
        $this->assertSame($canonical, $resolver->resolve('/shop/fibreglass/new-product')['canonical_path']);
        $this->assertSame(1, $product->urlAliases()->count());

        $product = $manager->save($this->attributes($brand, $supplier, $type, [
            'sku' => $product->sku, 'name' => $product->name, 'slug' => 'new-product', 'category_ids' => [$secondary->id, $nextPrimary->id],
            'primary_category_id' => $nextPrimary->id, 'status' => 'published',
        ]), $product);
        $this->assertSame(1, $product->urlAliases()->count(), 'An unchanged canonical path must not create duplicate history.');

        $product = $manager->save($this->attributes($brand, $supplier, $type, [
            'sku' => $product->sku, 'name' => $product->name, 'slug' => 'final-product', 'category_ids' => [$secondary->id, $nextPrimary->id],
            'primary_category_id' => $nextPrimary->id, 'status' => 'published',
        ]), $product);
        $latestCanonical = '/shop/outdoor/final-product';
        $this->assertSame($latestCanonical, $resolver->resolve($old)['canonical_path']);
        $this->assertSame($latestCanonical, $resolver->resolve($canonical)['canonical_path']);
        $this->assertSame(2, $product->urlAliases()->count());
    }

    public function test_archived_products_resolve_as_not_found_and_failed_changes_write_no_alias(): void
    {
        [$brand, $supplier, $type] = $this->references();
        $category = $this->category('pots');
        $manager = app(ProductCategoryManager::class);
        $resolver = app(ProductUrlResolver::class);
        $product = $manager->save($this->attributes($brand, $supplier, $type, [
            'category_ids' => [$category->id], 'primary_category_id' => $category->id, 'status' => 'published',
        ]));

        try {
            $manager->save($this->attributes($brand, $supplier, $type, [
                'sku' => $product->sku, 'name' => $product->name, 'slug' => 'new-url', 'category_ids' => [], 'primary_category_id' => null, 'status' => 'published',
            ]), $product);
            $this->fail('Invalid publication data must fail.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('product_url_aliases', 0);
            $this->assertSame('product', $product->fresh()->slug);
        }

        $manager->save($this->attributes($brand, $supplier, $type, [
            'sku' => $product->sku, 'name' => $product->name, 'slug' => $product->slug, 'category_ids' => [$category->id],
            'primary_category_id' => $category->id, 'status' => 'archived',
        ]), $product);
        $this->assertSame('not_found', $resolver->resolve('/shop/pots/product')['type']);
    }

    public function test_historical_path_cannot_be_reused_as_another_products_canonical_url(): void
    {
        [$brand, $supplier, $type] = $this->references();
        $category = $this->category('pots');
        $manager = app(ProductCategoryManager::class);
        $first = $manager->save($this->attributes($brand, $supplier, $type, [
            'category_ids' => [$category->id], 'primary_category_id' => $category->id, 'status' => 'published',
        ]));
        $manager->save($this->attributes($brand, $supplier, $type, [
            'sku' => $first->sku, 'name' => 'Renamed product', 'slug' => 'renamed-product',
            'category_ids' => [$category->id], 'primary_category_id' => $category->id, 'status' => 'published',
        ]), $first);
        $second = $manager->save($this->attributes($brand, $supplier, $type, ['sku' => 'SKU-2', 'slug' => 'another-product']));

        try {
            $manager->save($this->attributes($brand, $supplier, $type, [
                'sku' => $second->sku, 'name' => $second->name, 'slug' => 'product', 'category_ids' => [$category->id],
                'primary_category_id' => $category->id, 'status' => 'published',
            ]), $second);
            $this->fail('A historical URL must remain reserved for its original product.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('slug', $exception->errors());
            $this->assertSame('another-product', $second->fresh()->slug);
            $this->assertDatabaseCount('product_url_aliases', 1);
        }
    }

    public function test_og_uses_only_ready_override_and_falls_back_to_ready_main_image(): void
    {
        Storage::fake('public');
        [$brand, $supplier, $type] = $this->references();
        $product = app(ProductCategoryManager::class)->save($this->attributes($brand, $supplier, $type));
        $main = $this->readyAsset('main.webp');
        $override = $this->readyAsset('override.webp');
        ProductImage::query()->create(['product_id' => $product->id, 'media_asset_id' => $main->id, 'sort_order' => 0, 'is_main' => true]);

        $product->seo_og_media_asset_id = $override->id;
        $product->save();
        $this->assertSame('/storage/media/override.webp', $product->effective_og_image_url);

        $override->forceFill(['processing_status' => 'failed'])->save();
        $this->assertSame('/storage/media/main.webp', $product->fresh()->effective_og_image_url);

        ProductImage::query()->delete();
        $this->assertNull($product->fresh()->effective_og_image_url);
    }

    private function readyAsset(string $filename): MediaAsset
    {
        $path = 'media/'.$filename;
        Storage::disk('public')->put($path, 'webp');

        $asset = MediaAsset::query()->create(['disk' => 'local', 'path' => 'private/'.$filename, 'mime_type' => 'image/webp', 'size_bytes' => 4]);
        $asset->forceFill(['processing_status' => 'ready', 'variants' => ['full' => ['path' => $path]]])->save();

        return $asset;
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
            'sku' => 'SKU-'.Str::random(6), 'name' => 'Product', 'category_ids' => [], 'primary_category_id' => null, 'status' => 'draft',
        ], $overrides);
    }
}
