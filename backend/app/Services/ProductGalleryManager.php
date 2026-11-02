<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ProductGalleryManager
{
    private const DISK = 'local';

    private const DIRECTORY = 'product-originals';

    private const ALLOWED_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function upload(Product $product, UploadedFile $file): ProductImage
    {
        $mime = app(MediaImageValidator::class)->upload($file);
        $extension = self::ALLOWED_MIMES[$mime];
        $path = self::DIRECTORY.'/'.Str::uuid().'.'.$extension;
        $disk = Storage::disk(self::DISK);
        $storedPath = $disk->putFileAs(self::DIRECTORY, $file, basename($path));
        if ($storedPath !== $path || ! $disk->exists($path)) {
            if ($disk->exists($path) && ! MediaAsset::query()->where('disk', self::DISK)->where('path', $path)->exists()) {
                $disk->delete($path);
            }

            throw new RuntimeException('The product image could not be stored.');
        }

        try {
            return DB::transaction(function () use ($product, $path, $mime, $file): ProductImage {
                $asset = MediaAsset::query()->firstOrCreate(
                    ['disk' => self::DISK, 'path' => $path],
                    ['mime_type' => $mime, 'size_bytes' => (int) $file->getSize()],
                );

                app(MediaAssetDispatcher::class)->request($asset->refresh());

                return $this->attachLocked($product, $asset);
            });
        } catch (Throwable $exception) {
            // The path is newly generated for this upload; never remove a reused or pre-existing asset.
            if (! MediaAsset::query()->where('disk', self::DISK)->where('path', $path)->exists()) {
                $disk->delete($path);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<array{media_key?: string, upload?: UploadedFile, alt_override?: ?string}|UploadedFile>  $items
     * @return array<ProductImage>
     */
    public function uploadMany(Product $product, array $items, ?string $mainImageKey = null): array
    {
        if ($items === []) {
            return [];
        }

        $normalized = [];
        foreach ($items as $index => $item) {
            $key = is_array($item) ? (string) ($item['media_key'] ?? $index) : (string) $index;
            $file = is_array($item) ? ($item['upload'] ?? null) : $item;
            if (is_array($file)) {
                $file = count($file) === 1 ? reset($file) : null;
            }
            $alt = is_array($item) ? ($item['alt_override'] ?? null) : null;

            if (isset($normalized[$key]) || ($alt !== null && (! is_string($alt) || mb_strlen($alt) > 255))) {
                throw ValidationException::withMessages(['gallery_uploads' => 'Each image must have a unique key and an ALT override up to 255 characters.']);
            }

            $normalized[$key] = ['upload' => $file, 'alt' => $alt];
        }

        $mainImageKey ??= (string) array_key_first($normalized);
        if (! array_key_exists($mainImageKey, $normalized)) {
            throw ValidationException::withMessages(['main_image_key' => 'Choose one of the uploaded images as the main image.']);
        }

        $disk = Storage::disk(self::DISK);
        $stored = [];

        try {
            foreach ($normalized as $key => $image) {
                $file = $image['upload'];
                if (! $file instanceof UploadedFile) {
                    throw ValidationException::withMessages(['gallery_uploads' => 'Upload valid JPEG, PNG, or WebP images no larger than 10 MB each.']);
                }

                $mime = app(MediaImageValidator::class)->upload($file);
                $extension = self::ALLOWED_MIMES[$mime];
                do {
                    $path = self::DIRECTORY.'/'.Str::uuid().'.'.$extension;
                } while ($disk->exists($path) || MediaAsset::query()->where('disk', self::DISK)->where('path', $path)->exists());

                $storedPath = $disk->putFileAs(self::DIRECTORY, $file, basename($path));
                if ($storedPath !== $path || ! $disk->exists($path)) {
                    if ($disk->exists($path) && ! MediaAsset::query()->where('disk', self::DISK)->where('path', $path)->exists()) {
                        $disk->delete($path);
                    }

                    throw new RuntimeException('The product image could not be stored.');
                }

                $stored[] = ['key' => $key, 'path' => $path, 'mime' => $mime, 'size' => (int) $file->getSize(), 'alt' => $image['alt']];
            }

            return DB::transaction(function () use ($product, $stored, $mainImageKey): array {
                $images = [];
                foreach ($stored as $file) {
                    $asset = MediaAsset::query()->firstOrCreate(
                        ['disk' => self::DISK, 'path' => $file['path']],
                        ['mime_type' => $file['mime'], 'size_bytes' => $file['size']],
                    );
                    app(MediaAssetDispatcher::class)->request($asset->refresh());
                    $images[$file['key']] = $this->attachLocked($product, $asset, $file['alt']);
                }

                $this->setMainLocked($product, $images[$mainImageKey]);

                return array_values($images);
            }, 3);
        } catch (Throwable $exception) {
            foreach ($stored as $file) {
                if (! MediaAsset::query()->where('disk', self::DISK)->where('path', $file['path'])->exists()) {
                    $disk->delete($file['path']);
                }
            }

            throw $exception;
        }
    }

    public function attach(Product $product, MediaAsset $asset): ProductImage
    {
        $registeredProductFile = ($asset->disk === self::DISK && str_starts_with($asset->path, self::DIRECTORY.'/'))
            || ($asset->disk === 'public' && str_starts_with($asset->path, 'product-images/'));
        if (! $registeredProductFile || str_contains($asset->path, '..') || str_contains($asset->path, '\\')) {
            throw ValidationException::withMessages(['media_asset_id' => 'Only registered product image files can be attached.']);
        }

        if (! Storage::disk($asset->disk)->exists($asset->path)) {
            throw ValidationException::withMessages(['media_asset_id' => 'The selected media file is unavailable.']);
        }

        return DB::transaction(fn (): ProductImage => $this->attachLocked($product, $asset));
    }

    public function setMain(ProductImage $image): void
    {
        DB::transaction(function () use ($image): void {
            $product = Product::query()->whereKey($image->product_id)->lockForUpdate()->firstOrFail();
            $target = ProductImage::query()->whereKey($image->id)->where('product_id', $product->id)->lockForUpdate()->firstOrFail();
            $this->setMainLocked($product, $target);
        }, 3);
    }

    public function reorder(Product $product, array $imageIds): void
    {
        DB::transaction(function () use ($product, $imageIds): void {
            $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $images = $lockedProduct->images()->orderBy('id')->lockForUpdate()->get();
            if (count($imageIds) !== $images->count() || array_diff($images->modelKeys(), $imageIds) !== [] || array_diff($imageIds, $images->modelKeys()) !== []) {
                throw ValidationException::withMessages(['images' => 'The gallery order must contain every attached image exactly once.']);
            }

            foreach (array_values($imageIds) as $position => $id) {
                ProductImage::query()->where('product_id', $lockedProduct->id)->whereKey($id)->update(['sort_order' => $position]);
            }
        }, 3);
    }

    public function updateAlt(ProductImage $image, ?string $alt): void
    {
        $image->update(['alt_override' => filled($alt) ? trim($alt) : null]);
    }

    public function detach(ProductImage $image): void
    {
        DB::transaction(function () use ($image): void {
            $product = Product::query()->whereKey($image->product_id)->lockForUpdate()->firstOrFail();
            $target = ProductImage::query()->whereKey($image->id)->where('product_id', $product->id)->lockForUpdate()->firstOrFail();
            $wasMain = $target->is_main;
            $target->delete();

            if ($wasMain) {
                $replacement = $product->images()->orderBy('sort_order')->orderBy('id')->lockForUpdate()->first();
                $replacement?->update(['is_main' => true]);
            }
        }, 3);
    }

    private function attachLocked(Product $product, MediaAsset $asset, ?string $altOverride = null): ProductImage
    {
        $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
        if ($lockedProduct->images()->where('media_asset_id', $asset->id)->exists()) {
            throw ValidationException::withMessages(['media_asset_id' => 'This image is already attached to the product.']);
        }

        $sortOrder = (int) ($lockedProduct->images()->max('sort_order') ?? -1) + 1;
        try {
            $image = $lockedProduct->images()->create([
                'media_asset_id' => $asset->id,
                'sort_order' => $sortOrder,
                'is_main' => ! $lockedProduct->images()->where('is_main', true)->exists(),
                'alt_override' => $altOverride,
            ]);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw ValidationException::withMessages(['media_asset_id' => 'This image is already attached to the product.']);
            }

            throw $exception;
        }

        return $image->load(['mediaAsset', 'product']);
    }

    private function setMainLocked(Product $product, ProductImage $image): void
    {
        ProductImage::query()->where('product_id', $product->id)->where('is_main', true)->update(['is_main' => false]);
        ProductImage::query()->whereKey($image->id)->where('product_id', $product->id)->update(['is_main' => true]);
    }
}
