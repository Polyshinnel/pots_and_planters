<?php

namespace App\Services;

use App\Exceptions\MediaProcessingException;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Throwable;

class MediaAssetProcessor
{
    public function process(int $id, string $token): void
    {
        $asset = DB::transaction(function () use ($id, $token): ?MediaAsset {
            $asset = MediaAsset::query()->whereKey($id)->lockForUpdate()->first();
            if (! $asset || $asset->processing_token !== $token || $asset->processing_status !== 'pending') {
                return null;
            }
            $asset->forceFill(['processing_status' => 'processing', 'processing_started_at' => now(), 'processing_error' => null])->save();

            return $asset;
        });
        if (! $asset) {
            return;
        }

        if (config('media.driver') !== 'gd' || ! extension_loaded('gd') || ! extension_loaded('exif')
            || ! (gd_info()['WebP Support'] ?? false) || ! (gd_info()['JPEG Support'] ?? false) || ! (gd_info()['PNG Support'] ?? false)) {
            throw new MediaProcessingException('Configured GD driver requires JPEG, PNG, WebP and EXIF support.');
        }

        $source = Storage::disk($asset->disk);
        $private = Storage::disk('local');
        $public = Storage::disk('public');
        foreach ([$source, $private, $public] as $disk) {
            if (! $disk->getAdapter() instanceof LocalFilesystemAdapter) {
                throw new MediaProcessingException('Media publication requires the configured shared local filesystem.');
            }
        }

        $staging = 'media-processing/'.$asset->public_id.'/'.$token;
        $destination = 'product-media/'.$asset->public_id.'/'.$token;
        $published = false;
        $committed = false;

        try {
            app(MediaImageValidator::class)->inspect($source->path($asset->path), $asset->mime_type);
            $manager = new ImageManager(new Driver, autoOrientation: true, decodeAnimation: false, strip: true);
            try {
                $image = $manager->read($source->path($asset->path));
            } catch (Throwable) {
                throw new MediaProcessingException('The source image cannot be decoded. Upload an intact JPEG, PNG or WebP.');
            }
            if (! $private->makeDirectory($staging)) {
                throw new MediaProcessingException('Unable to prepare image processing directory.');
            }

            $variants = [];
            foreach (config('media.variants') as $name => $size) {
                $variant = clone $image;
                // Fit the source proportionally inside the square, then add a
                // transparent canvas only along the shorter dimension.
                $variant->scale($size, $size)->resizeCanvas($size, $size, 'transparent', 'center');
                $bytes = (string) $variant->toWebp(quality: max(1, min(100, config('media.quality'))), strip: true);
                $path = $staging.'/'.$name.'.webp';
                if ($bytes === '' || ! $private->put($path, $bytes)) {
                    throw new MediaProcessingException('Unable to write an image variant.');
                }
                $info = @getimagesize($private->path($path));
                if ($info === false || $info[0] !== $size || $info[1] !== $size || $info['mime'] !== 'image/webp') {
                    throw new MediaProcessingException('Image variant verification failed.');
                }
                if (! $private->setVisibility($path, 'public')) {
                    throw new MediaProcessingException('Unable to prepare public image variant permissions.');
                }
                $variants[$name] = ['path' => $destination.'/'.$name.'.webp', 'width' => $size, 'height' => $size, 'size_bytes' => strlen($bytes)];
                unset($variant, $bytes);
            }

            // Rename preserves permissions: nginx must be able to traverse the published directory.
            // Until publication, the private parent directories still protect the complete staging set.
            if (! $private->setVisibility($staging, 'public')) {
                throw new MediaProcessingException('Unable to prepare public image directory permissions.');
            }

            $committed = DB::transaction(function () use ($id, $token, $variants, $public, $private, $staging, $destination, &$published): bool {
                $current = MediaAsset::query()->whereKey($id)->lockForUpdate()->first();
                if (! $current || $current->processing_token !== $token || $current->processing_status !== 'processing') {
                    return false;
                }
                // POSIX directory rename publishes all three verified files together on the shared volume.
                if (! $public->makeDirectory(dirname($destination)) || $public->exists($destination)
                    || ! @rename($private->path($staging), $public->path($destination))) {
                    throw new MediaProcessingException('Unable to atomically publish image variants.');
                }
                $published = true;
                $current->forceFill([
                    'variants' => $variants, 'processing_status' => 'ready', 'processing_error' => null,
                    'processing_finished_at' => now(),
                ])->save();

                return true;
            });
        } finally {
            $private->deleteDirectory($staging);
            if ($published && ! $committed) {
                $public->deleteDirectory($destination);
            }
        }
    }
}
