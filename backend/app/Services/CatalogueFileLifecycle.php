<?php

namespace App\Services;

use App\Models\Catalogue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CatalogueFileLifecycle
{
    /** @return array{pdf_path: ?string, cover_path: ?string} */
    public function paths(Catalogue $catalogue): array
    {
        return ['pdf_path' => $catalogue->pdf_path, 'cover_path' => $catalogue->cover_path];
    }

    /** Delete only catalogue-owned files that are no longer referenced. */
    public function clean(array $paths): void
    {
        foreach (['pdf_path' => 'local', 'cover_path' => 'public'] as $attribute => $disk) {
            $path = $paths[$attribute] ?? null;
            if (! is_string($path) || $path === '' || str_contains($path, '..')) {
                continue;
            }
            if (Catalogue::query()->where($attribute, $path)->exists()) {
                continue;
            }
            try {
                $storage = Storage::disk($disk);
                if ($storage->exists($path) && ! $storage->delete($path)) {
                    Log::warning('Could not remove unused catalogue file.', ['disk' => $disk, 'path' => $path]);
                }
            } catch (Throwable $exception) {
                Log::warning('Could not remove unused catalogue file.', [
                    'disk' => $disk,
                    'path' => $path,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    public function cleanupNew(array $old, array $new): void
    {
        $this->clean([
            'pdf_path' => ($old['pdf_path'] ?? null) === ($new['pdf_path'] ?? null) ? null : ($new['pdf_path'] ?? null),
            'cover_path' => ($old['cover_path'] ?? null) === ($new['cover_path'] ?? null) ? null : ($new['cover_path'] ?? null),
        ]);
    }
}
