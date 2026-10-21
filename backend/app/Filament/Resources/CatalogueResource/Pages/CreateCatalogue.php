<?php

namespace App\Filament\Resources\CatalogueResource\Pages;

use App\Filament\Resources\CatalogueResource;
use App\Models\Catalogue;
use App\Services\CatalogueFileLifecycle;
use App\Services\CatalogueManager;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;
use Throwable;

class CreateCatalogue extends CreateRecord
{
    protected static string $resource = CatalogueResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['slug'] ?? null)) {
            $data['slug'] = CatalogueResource::generateUniqueSlug((string) ($data['title'] ?? ''));
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Catalogue
    {
        $categoryIds = array_map('intval', $data['category_ids'] ?? []);
        unset($data['category_ids']);
        try {
            return app(CatalogueManager::class)->save($data, $categoryIds);
        } catch (Throwable $exception) {
            app(CatalogueFileLifecycle::class)->cleanupNew([], Arr::only($data, ['pdf_path', 'cover_path']));
            throw $exception;
        }
    }
}
