<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\RelationManagers\GalleryRelationManager;
use App\Jobs\ProcessMediaAsset;
use App\Models\Brand;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Supplier;
use App\Models\User;
use App\Services\MediaAssetDispatcher;
use App\Services\MediaAssetProcessor;
use App\Services\MediaImageValidator;
use App\Services\ProductGalleryManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Livewire\Livewire;
use Tests\TestCase;

class MediaOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local', ['directory_visibility' => 'private']);
        Storage::fake('public');
        Queue::fake();
    }

    public function test_upload_is_private_pending_and_only_enqueues_after_outer_commit(): void
    {
        $product = $this->product();
        DB::beginTransaction();
        $image = app(ProductGalleryManager::class)->upload($product, UploadedFile::fake()->image('photo.jpg', 800, 400));
        Queue::assertNothingPushed();
        $asset = $image->mediaAsset->fresh();
        $this->assertSame('pending', $asset->processing_status);
        $this->assertSame('local', $asset->disk);
        $this->assertNull($asset->variants);
        $this->assertNull($asset->variantUrl());
        Storage::disk('local')->assertExists($asset->path);
        $this->assertSame([], Storage::disk('public')->allFiles());
        DB::commit();
        Queue::assertPushedOn('default', ProcessMediaAsset::class, fn ($job): bool => $job->assetId === $asset->id && $job->connection === 'redis');
        $this->assertArrayNotHasKey('path', $asset->toArray());
    }

    public function test_rollback_does_not_enqueue_or_leave_a_media_record(): void
    {
        DB::beginTransaction();
        $asset = $this->asset();
        app(MediaAssetDispatcher::class)->request($asset);
        DB::rollBack();
        Queue::assertNothingPushed();
        $this->assertDatabaseMissing('media_assets', ['id' => $asset->id]);
    }

    public function test_real_gd_driver_converts_jpeg_png_and_webp_into_exactly_three_variants(): void
    {
        $this->assertTrue(gd_info()['WebP Support']);
        $this->assertTrue(extension_loaded('exif'));
        foreach (['jpg', 'png', 'webp'] as $format) {
            $asset = $this->asset($format, 1400, 700);
            $this->process($asset);
            $asset->refresh();
            $this->assertSame('ready', $asset->processing_status);
            $this->assertSame(['full', 'card', 'thumb'], array_keys($asset->variants));
            foreach (config('media.variants') as $name => $size) {
                $path = $asset->variants[$name]['path'];
                $info = getimagesize(Storage::disk('public')->path($path));
                $this->assertSame([$size, $size, 'image/webp'], [$info[0], $info[1], $info['mime']]);
                $this->assertNotNull($asset->variantUrl($name));
            }
            $this->assertNotNull($asset->processing_finished_at);
        }
        $this->assertCount(9, Storage::disk('public')->allFiles());
        $this->assertSame([], Storage::disk('local')->allFiles('media-processing'));
    }

    public function test_small_images_are_centered_without_upscale_and_keep_alpha(): void
    {
        $asset = $this->asset('png', 40, 20);
        $this->process($asset);
        foreach (config('media.variants') as $name => $size) {
            $image = ImageManager::gd()->read(Storage::disk('public')->path($asset->fresh()->variants[$name]['path']));
            $this->assertTrue($image->pickColor(0, 0)->isTransparent());
            $this->assertFalse($image->pickColor((int) ($size / 2), (int) ($size / 2))->isTransparent());
            $this->assertTrue($image->pickColor((int) ($size / 2) - 25, (int) ($size / 2))->isTransparent());
        }
    }

    public function test_published_directory_and_files_are_readable_by_nginx_while_the_original_directory_remains_private(): void
    {
        $asset = $this->asset();
        $this->process($asset);
        $public = Storage::disk('public');
        foreach ($asset->fresh()->variants as $variant) {
            $this->assertSame(0755, fileperms($public->path(dirname($variant['path']))) & 0777);
            $this->assertSame(0644, fileperms($public->path($variant['path'])) & 0777);
        }
        $this->assertSame(0700, fileperms(Storage::disk('local')->path(dirname($asset->path))) & 0777);
    }

    public function test_portrait_landscape_and_transparent_source_preserve_proportions_and_alpha(): void
    {
        foreach ([[1200, 600], [600, 1200]] as [$width, $height]) {
            $asset = $this->asset('png', $width, $height);
            $this->process($asset);
            $thumb = ImageManager::gd()->read(Storage::disk('public')->path($asset->fresh()->variants['thumb']['path']));
            $this->assertTrue($thumb->pickColor(0, 0)->isTransparent());
            $this->assertFalse($thumb->pickColor(50, 50)->isTransparent());
            $this->assertTrue($thumb->pickColor($width > $height ? 50 : 5, $width > $height ? 5 : 50)->isTransparent());
        }
        $asset = $this->asset();
        Storage::disk('local')->put($asset->path, (string) ImageManager::gd()->create(80, 40)->toPng());
        $this->process($asset);
        $thumb = ImageManager::gd()->read(Storage::disk('public')->path($asset->fresh()->variants['thumb']['path']));
        $this->assertTrue($thumb->pickColor(50, 50)->isTransparent());
    }

    public function test_exif_orientation_is_applied_before_scaling_and_metadata_is_stripped(): void
    {
        $asset = $this->asset('jpg', 80, 40);
        $jpeg = Storage::disk('local')->get($asset->path);
        // Minimal standards-compliant EXIF orientation=6 (rotate 90 degrees clockwise).
        $exif = "Exif\0\0".hex2bin('49492a0008000000010012010300010000000600000000000000');
        $jpeg = substr($jpeg, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
        Storage::disk('local')->put($asset->path, $jpeg);
        $this->process($asset);
        $bytes = Storage::disk('public')->get($asset->fresh()->variants['thumb']['path']);
        $thumb = ImageManager::gd()->read($bytes);
        $this->assertTrue($thumb->pickColor(20, 50)->isTransparent());
        $this->assertFalse($thumb->pickColor(50, 20)->isTransparent());
        $this->assertStringNotContainsString('Exif', $bytes);
        $this->assertStringNotContainsString('EXIF', $bytes);
    }

    public function test_repeated_and_overlapping_jobs_keep_the_ready_set_unchanged(): void
    {
        $asset = $this->asset();
        $job = $this->queuedJob($asset);
        $job->handle(app(MediaAssetProcessor::class));
        $before = $asset->fresh()->variants;
        $job->handle(app(MediaAssetProcessor::class));
        $job->failed(new \RuntimeException('late duplicate failure'));
        $this->assertFalse(app(MediaAssetDispatcher::class)->request($asset->fresh()));
        $this->assertSame($before, $asset->fresh()->variants);
        $this->assertSame('ready', $asset->fresh()->processing_status);
        $this->assertCount(3, Storage::disk('public')->allFiles());

        $other = $this->asset();
        $job = $this->queuedJob($other);
        $other->forceFill(['processing_status' => 'processing', 'processing_started_at' => now()])->save();
        $job->handle(app(MediaAssetProcessor::class));
        $this->assertSame('processing', $other->fresh()->processing_status);
        $this->assertCount(3, Storage::disk('public')->allFiles());
    }

    public function test_enqueue_outage_keeps_pending_and_allows_one_safe_retry(): void
    {
        $asset = $this->asset();
        $queue = Queue::getFacadeRoot();
        Queue::shouldReceive('connection')->once()->with('redis')->andThrow(new \RuntimeException('/private/secret'));
        $this->assertTrue(app(MediaAssetDispatcher::class)->request($asset));
        $this->assertSame('pending', $asset->fresh()->processing_status);
        $this->assertNull($asset->fresh()->queued_at);
        $this->assertStringNotContainsString('secret', $asset->fresh()->processing_error);
        Queue::swap($queue);
        Queue::fake();
        $this->assertTrue(app(MediaAssetDispatcher::class)->request($asset->fresh()));
        $this->assertFalse(app(MediaAssetDispatcher::class)->request($asset->fresh()));
        Queue::assertPushed(ProcessMediaAsset::class, 1);
    }

    public function test_corrupt_or_missing_source_fails_with_safe_error_and_retry_recovers(): void
    {
        foreach (['corrupt', 'missing'] as $failure) {
            $asset = $this->asset();
            $valid = Storage::disk('local')->get($asset->path);
            if ($failure === 'corrupt') {
                Storage::disk('local')->put($asset->path, 'not an image');
            } else {
                Storage::disk('local')->delete($asset->path);
            }
            $job = $this->queuedJob($asset);
            try {
                $job->handle(app(MediaAssetProcessor::class));
                $this->fail('Invalid source must fail.');
            } catch (\RuntimeException $exception) {
                $this->assertStringNotContainsString($asset->path, $exception->getMessage());
                $job->failed($exception);
            }
            $this->assertSame('failed', $asset->fresh()->processing_status);
            $this->assertNull($asset->fresh()->variantUrl());
            Storage::disk('local')->put($asset->path, $valid);
            $this->process($asset->fresh());
            $this->assertSame('ready', $asset->fresh()->processing_status);
        }
    }

    public function test_write_failure_cleans_staging_without_publishing_a_partial_set(): void
    {
        $asset = $this->asset();
        $job = $this->queuedJob($asset);
        $disk = Storage::disk('local');
        $mock = \Mockery::mock($disk)->makePartial();
        $mock->shouldReceive('put')->once()->andReturnFalse();
        Storage::set('local', $mock);
        try {
            $job->handle(app(MediaAssetProcessor::class));
            $this->fail('A false write result must fail.');
        } catch (\RuntimeException) {
            $this->assertSame([], Storage::disk('public')->allFiles());
            $this->assertSame([], $disk->allFiles('media-processing'));
            $this->assertNull($asset->fresh()->variants);
        }
    }

    public function test_stale_claim_cannot_publish_after_admin_requests_another_generation(): void
    {
        $asset = $this->asset();
        $job = $this->queuedJob($asset);
        $validator = \Mockery::mock(MediaImageValidator::class);
        $validator->shouldReceive('inspect')->once()->andReturnUsing(function () use ($asset): string {
            $asset->refresh()->forceFill(['processing_started_at' => now()->subMinutes(10)])->save();
            $this->assertTrue(app(MediaAssetDispatcher::class)->request($asset));

            return 'image/png';
        });
        $this->app->instance(MediaImageValidator::class, $validator);
        $job->handle(app(MediaAssetProcessor::class));
        $this->assertSame('pending', $asset->fresh()->processing_status);
        $this->assertNotSame($job->token, $asset->fresh()->processing_token);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame([], Storage::disk('local')->allFiles('media-processing'));
    }

    public function test_database_failure_during_publication_removes_only_the_new_generation(): void
    {
        $ready = $this->asset();
        $this->process($ready);
        $existing = $ready->fresh()->variants;
        $asset = $this->asset();
        $job = $this->queuedJob($asset);
        Event::listen('eloquent.updating: '.MediaAsset::class, function (MediaAsset $record) use ($asset): void {
            if ($record->id === $asset->id && $record->processing_status === 'ready') {
                throw new \RuntimeException('Simulated database failure');
            }
        });
        try {
            $job->handle(app(MediaAssetProcessor::class));
            $this->fail('Database failure must not leave a published generation.');
        } catch (\RuntimeException) {
            $this->assertNull($asset->fresh()->variants);
            $this->assertCount(3, Storage::disk('public')->allFiles());
            $this->assertSame($existing, $ready->fresh()->variants);
            $this->assertSame([], Storage::disk('local')->allFiles('media-processing'));
        }
    }

    public function test_decoder_rejects_a_truncated_image_even_when_its_header_is_valid(): void
    {
        $asset = $this->asset();
        Storage::disk('local')->put($asset->path, substr(Storage::disk('local')->get($asset->path), 0, 33));
        $this->assertSame('image/png', app(MediaImageValidator::class)->inspect(Storage::disk('local')->path($asset->path), 'image/png'));
        try {
            $this->process($asset);
            $this->fail('Truncated content must fail during decoding.');
        } catch (\RuntimeException) {
            $this->assertNull($asset->fresh()->variants);
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    public function test_exactly_ten_mib_is_accepted_and_low_memory_is_rejected_before_decode(): void
    {
        $bytes = (string) ImageManager::gd()->create(20, 10)->fill('red')->toPng();
        $file = UploadedFile::fake()->createWithContent('limit.png', str_pad($bytes, 10 * 1024 * 1024, 'x'));
        $this->assertSame('image/png', app(MediaImageValidator::class)->upload($file));
        $previous = ini_get('memory_limit');
        try {
            ini_set('memory_limit', (string) (memory_get_usage(true) + 1024 * 1024));
            $this->expectException(ValidationException::class);
            app(MediaImageValidator::class)->upload($file);
        } finally {
            ini_set('memory_limit', $previous);
        }
    }

    public function test_deleted_asset_cannot_be_recreated_or_publish_variants(): void
    {
        $asset = $this->asset();
        $job = $this->queuedJob($asset);
        $validator = \Mockery::mock(MediaImageValidator::class);
        $validator->shouldReceive('inspect')->once()->andReturnUsing(function () use ($asset): string {
            $asset->delete();

            return 'image/png';
        });
        $this->app->instance(MediaImageValidator::class, $validator);
        $job->handle(app(MediaAssetProcessor::class));
        $this->assertDatabaseMissing('media_assets', ['id' => $asset->id]);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_limits_actual_type_and_broken_upload_are_checked_before_asset_creation(): void
    {
        $valid = (string) ImageManager::gd()->create(20, 10)->fill('red')->toPng();
        $files = [
            UploadedFile::fake()->createWithContent('broken.png', 'not an image'),
            UploadedFile::fake()->createWithContent('oversize.png', $valid.str_repeat('x', 10 * 1024 * 1024)),
            UploadedFile::fake()->image('huge.png', 4000, 4000),
        ];
        foreach ($files as $file) {
            try {
                app(ProductGalleryManager::class)->upload($this->product(), $file);
                $this->fail('Invalid source must be rejected.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('media_assets', 0);
            }
        }
        $asset = $this->asset();
        $this->expectException(\RuntimeException::class);
        app(MediaImageValidator::class)->inspect(Storage::disk('local')->path($asset->path), 'image/jpeg');
    }

    public function test_missing_webp_driver_has_no_fallback(): void
    {
        $asset = $this->asset();
        config(['media.driver' => 'imagick']);
        try {
            $this->process($asset);
            $this->fail('Unsupported driver must fail explicitly.');
        } catch (\RuntimeException) {
            $this->assertNull($asset->fresh()->variantUrl());
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    public function test_legacy_asset_is_preserved_reusable_and_only_converted_explicitly(): void
    {
        $bytes = (string) ImageManager::gd()->create(80, 40)->fill('red')->toJpeg();
        Storage::disk('public')->put('product-images/legacy.jpg', $bytes);
        $asset = MediaAsset::query()->create(['disk' => 'public', 'path' => 'product-images/legacy.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => strlen($bytes)]);
        $asset->refresh();
        Queue::assertNothingPushed();
        $this->assertNull($asset->variantUrl());
        $manager = app(ProductGalleryManager::class);
        $first = $manager->attach($this->product(), $asset);
        $second = $manager->attach($this->product(), $asset);
        $manager->detach($first);
        $this->assertNotNull($second->fresh());
        $this->process($asset);
        $this->assertNotNull($asset->fresh()->variantUrl());
        $this->assertSame($bytes, Storage::disk('public')->get('product-images/legacy.jpg'));
    }

    public function test_ready_url_is_withheld_when_a_variant_file_is_missing(): void
    {
        $asset = $this->asset();
        $this->process($asset);
        Storage::disk('public')->delete($asset->fresh()->variants['thumb']['path']);
        $this->assertNull($asset->fresh()->variantUrl());
    }

    public function test_admin_retry_is_visible_for_failed_and_stale_pending_and_prevents_double_dispatch(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $image = app(ProductGalleryManager::class)->upload($this->product(), UploadedFile::fake()->image('photo.png'));
        $image->mediaAsset->forceFill(['processing_status' => 'failed'])->save();
        Queue::fake();
        Livewire::actingAs(User::factory()->create(['is_admin' => true]))
            ->test(GalleryRelationManager::class, ['ownerRecord' => $image->product, 'pageClass' => EditProduct::class])
            ->assertTableActionVisible('retryProcessing', $image)
            ->callTableAction('retryProcessing', $image)
            ->assertHasNoTableActionErrors()
            ->assertTableActionHidden('retryProcessing', $image);
        Queue::assertPushed(ProcessMediaAsset::class, 1);
        $image->mediaAsset->refresh()->forceFill(['queued_at' => now()->subMinutes(10)])->save();
        $this->assertTrue($image->mediaAsset->canRetryProcessing());
        Livewire::actingAs(User::factory()->create(['is_admin' => false]))
            ->test(GalleryRelationManager::class, ['ownerRecord' => $image->product, 'pageClass' => EditProduct::class])
            ->assertTableActionHidden('retryProcessing', $image);
    }

    private function queuedJob(MediaAsset $asset): ProcessMediaAsset
    {
        $this->assertTrue(app(MediaAssetDispatcher::class)->request($asset));

        return new ProcessMediaAsset($asset->id, $asset->fresh()->processing_token);
    }

    private function process(MediaAsset $asset): void
    {
        $this->queuedJob($asset)->handle(app(MediaAssetProcessor::class));
    }

    private function asset(string $extension = 'png', int $width = 80, int $height = 40): MediaAsset
    {
        $image = (new ImageManager(new Driver))->create($width, $height)->fill('red');
        $bytes = (string) $image->encodeByExtension($extension);
        $path = 'product-originals/'.Str::uuid().'.'.$extension;
        Storage::disk('local')->put($path, $bytes);

        return MediaAsset::query()->create([
            'disk' => 'local', 'path' => $path,
            'mime_type' => $extension === 'jpg' ? 'image/jpeg' : 'image/'.$extension, 'size_bytes' => strlen($bytes),
        ])->refresh();
    }

    private function product(): Product
    {
        return Product::query()->create([
            'brand_id' => Brand::query()->create(['name' => 'Media Brand'])->id,
            'supplier_id' => Supplier::query()->create(['name' => 'Media Supplier'])->id,
            'product_type_id' => ProductType::query()->create(['name' => 'Media Type'])->id,
            'sku' => Str::upper(Str::random(10)), 'name' => 'Media product', 'status' => 'draft',
        ]);
    }
}
