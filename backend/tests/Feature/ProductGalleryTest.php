<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\RelationManagers\GalleryRelationManager;
use App\Models\Brand;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProductCategoryManager;
use App\Services\ProductGalleryManager;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ProductGalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Queue::fake();
        Storage::fake('local');
    }

    public function test_first_upload_is_main_and_later_upload_does_not_change_main(): void
    {
        Storage::fake('public');
        $product = $this->product('Planter');
        $manager = app(ProductGalleryManager::class);

        $first = $manager->upload($product, $this->image());
        $second = $manager->upload($product, $this->image());

        $this->assertTrue($first->fresh()->is_main);
        $this->assertFalse($second->fresh()->is_main);
        $this->assertSame(0, $first->sort_order);
        $this->assertSame(1, $second->sort_order);
        Storage::disk('local')->assertExists($first->mediaAsset->path);
        Storage::disk('local')->assertExists($second->mediaAsset->path);
    }

    public function test_main_is_independent_of_reorder_and_deleted_main_is_replaced_deterministically(): void
    {
        Storage::fake('public');
        $product = $this->product('Pot');
        $manager = app(ProductGalleryManager::class);
        $first = $manager->upload($product, $this->image());
        $second = $manager->upload($product, $this->image());
        $third = $manager->upload($product, $this->image());

        $manager->setMain($third);
        $manager->reorder($product, [$third->id, $first->id, $second->id]);
        $this->assertTrue($third->fresh()->is_main);
        $this->assertSame([0, 1, 2], $product->images()->orderBy('sort_order')->pluck('sort_order')->all());

        $manager->detach($third->fresh());
        $this->assertTrue($first->fresh()->is_main);
        $manager->detach($second->fresh());
        $manager->detach($first->fresh());
        $this->assertDatabaseMissing('product_images', ['product_id' => $product->id, 'is_main' => true]);
    }

    public function test_media_asset_can_be_shared_and_detach_keeps_file_and_other_product_link(): void
    {
        Storage::fake('public');
        $firstProduct = $this->product('First');
        $secondProduct = $this->product('Second');
        $manager = app(ProductGalleryManager::class);
        $image = $manager->upload($firstProduct, $this->image());
        $assetId = $image->media_asset_id;
        $path = $image->mediaAsset->path;
        $secondImage = $manager->attach($secondProduct, $image->mediaAsset);

        $manager->detach($image);

        $this->assertDatabaseHas('product_images', ['product_id' => $secondProduct->id, 'media_asset_id' => $assetId]);
        $this->assertDatabaseHas('media_assets', ['id' => $assetId, 'disk' => 'local', 'path' => $path]);
        Storage::disk('local')->assertExists($path);
        $this->assertTrue($secondImage->fresh()->is_main);
    }

    public function test_database_rejects_duplicate_assets_duplicate_product_links_and_multiple_main_images(): void
    {
        Storage::fake('public');
        $product = $this->product('Pot');
        $manager = app(ProductGalleryManager::class);
        $first = $manager->upload($product, $this->image());
        $second = $manager->upload($product, $this->image());

        try {
            $second->update(['is_main' => true]);
            $this->fail('MariaDB must reject a second main image for the product.');
        } catch (QueryException) {
            $this->assertTrue($first->fresh()->is_main);
        }

        try {
            $product->images()->create(['media_asset_id' => $first->media_asset_id, 'sort_order' => 10]);
            $this->fail('MariaDB must reject a duplicate asset in the same gallery.');
        } catch (QueryException) {
            $this->assertDatabaseCount('product_images', 2);
        }

        try {
            $first->mediaAsset->delete();
            $this->fail('MariaDB must restrict deletion of media assets that still have gallery links.');
        } catch (QueryException) {
            $this->assertDatabaseHas('media_assets', ['id' => $first->media_asset_id]);
        }

        try {
            MediaAsset::query()->create(['disk' => 'local', 'path' => $first->mediaAsset->path, 'mime_type' => 'image/jpeg', 'size_bytes' => 1]);
            $this->fail('MariaDB must reject duplicate (disk, path).');
        } catch (QueryException) {
            $this->assertDatabaseCount('media_assets', 2);
        }
    }

    public function test_alt_override_survives_product_rename_and_reorder_and_auto_alt_uses_only_name(): void
    {
        Storage::fake('public');
        $product = $this->product('Known Product');
        $manager = app(ProductGalleryManager::class);
        $first = $manager->upload($product, $this->image());
        $second = $manager->upload($product, $this->image());
        $manager->updateAlt($second, 'Manual view of the planter');
        $product->update(['name' => 'Renamed Product']);
        $manager->reorder($product, [$second->id, $first->id]);

        $this->assertSame('Manual view of the planter', $second->fresh()->effective_alt);
        $this->assertSame('Product image of Renamed Product 2', $first->fresh()->effective_alt);
        $this->assertNull($first->fresh()->alt_override);
    }

    public function test_invalid_upload_is_rejected_without_creating_file_or_relations(): void
    {
        Storage::fake('public');
        $product = $this->product('Pot');

        try {
            app(ProductGalleryManager::class)->upload($product, UploadedFile::fake()->create('script.php', 10, 'text/x-php'));
            $this->fail('Unsupported uploads must be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('media_assets', 0);
            $this->assertDatabaseCount('product_images', 0);
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    public function test_admin_gallery_relation_manager_can_set_main_and_detach_without_deleting_asset(): void
    {
        Storage::fake('public');
        $product = $this->product('Pot');
        $image = app(ProductGalleryManager::class)->upload($product, $this->image());
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)->test(GalleryRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->assertCanSeeTableRecords([$image])
            ->callTableAction('detach', $image)
            ->assertSuccessful();

        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
        Storage::disk('local')->assertExists($image->mediaAsset->path);
    }

    public function test_product_create_form_accepts_gallery_images_and_attaches_them_after_product_creation(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $brand = Brand::query()->create(['name' => 'Create Brand']);
        $supplier = Supplier::query()->create(['name' => 'Create Supplier']);
        $type = ProductType::query()->create(['name' => 'Create Type']);

        Livewire::actingAs($admin)->test(CreateProduct::class)
            ->assertFormFieldExists('gallery_uploads')
            ->assertFormFieldExists('main_image_key')
            ->set('data.brand_id', $brand->id)
            ->set('data.supplier_id', $supplier->id)
            ->set('data.product_type_id', $type->id)
            ->set('data.sku', 'CREATE-IMAGE-1')
            ->set('data.name', 'Product created with image')
            ->set('data.gallery_uploads', [
                'first' => ['media_key' => 'first', 'alt_override' => 'Front view'],
                'second' => ['media_key' => 'second', 'alt_override' => null],
            ])
            ->set('data.gallery_uploads.first.upload', [$this->image()])
            ->set('data.gallery_uploads.second.upload', [$this->image()])
            ->set('data.main_image_key', 'second')
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('sku', 'CREATE-IMAGE-1')->firstOrFail();
        $this->assertDatabaseCount('product_images', 2);
        $this->assertDatabaseHas('product_images', ['product_id' => $product->id, 'sort_order' => 0, 'alt_override' => 'Front view', 'is_main' => false]);
        $this->assertDatabaseHas('product_images', ['product_id' => $product->id, 'sort_order' => 1, 'alt_override' => null, 'is_main' => true]);
        $images = $product->images()->orderBy('sort_order')->get();
        $this->assertSame('Front view', $images[0]->effective_alt);
        $this->assertSame('Product image of Product created with image 2', $images[1]->effective_alt);
        foreach ($images as $image) {
            Storage::disk('local')->assertExists($image->mediaAsset->path);
        }
    }

    public function test_non_admin_cannot_open_product_gallery(): void
    {
        $product = $this->product('Pot');

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/admin/products/'.$product->id.'/edit')->assertForbidden();
    }

    private function product(string $name): Product
    {
        $brand = Brand::query()->create(['name' => 'Brand']);
        $supplier = Supplier::query()->create(['name' => 'Supplier']);
        $type = ProductType::query()->create(['name' => 'Planter']);

        return app(ProductCategoryManager::class)->save([
            'brand_id' => $brand->id,
            'supplier_id' => $supplier->id,
            'product_type_id' => $type->id,
            'sku' => Str::upper(Str::random(8)),
            'name' => $name,
            'status' => 'draft',
            'category_ids' => [],
            'primary_category_id' => null,
        ]);
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->image('image.png', 80, 40);
    }
}
