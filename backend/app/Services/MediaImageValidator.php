<?php

namespace App\Services;

use App\Exceptions\MediaProcessingException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MediaImageValidator
{
    public const MIME_EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function upload(UploadedFile $file): string
    {
        try {
            if (! $file->isValid()) {
                throw new RuntimeException('Invalid upload.');
            }

            return $this->inspect($file->getPathname(), $file->getMimeType());
        } catch (\Throwable) {
            throw ValidationException::withMessages(['upload' => 'Upload a valid JPEG, PNG, or WebP up to 10 MiB, 12 megapixels and 12000 pixels per side.']);
        }
    }

    public function inspect(string $path, ?string $expectedMime): string
    {
        $size = @filesize($path);
        $info = @getimagesize($path);
        $mime = $info['mime'] ?? null;
        if ($size === false || $size < 1 || $size > config('media.max_bytes') || $info === false
            || ! isset(self::MIME_EXTENSIONS[$mime]) || $mime !== $expectedMime
            || $info[0] < 1 || $info[1] < 1 || max($info[0], $info[1]) > config('media.max_dimension')
            || config('media.max_pixels') < $info[0] * $info[1]) {
            throw new MediaProcessingException('Image content or type is invalid, or exceeds 10 MiB, 12 megapixels or 12000 pixels per side.');
        }

        // Bound estimated GD decode/clone/canvas memory before decoding. PHP is also capped at 256M.
        $limit = ini_parse_quantity(ini_get('memory_limit'));
        if ($limit > 0 && memory_get_usage(true) + $info[0] * $info[1] * 12 + 32 * 1024 * 1024 > $limit) {
            throw new MediaProcessingException('Image exceeds the available processing memory.');
        }

        return $mime;
    }
}
