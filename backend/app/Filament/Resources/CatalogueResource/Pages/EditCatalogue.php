<?php

namespace App\Filament\Resources\CatalogueResource\Pages;

use App\Filament\Resources\CatalogueResource;
use App\Models\Catalogue;
use App\Services\CatalogueFileLifecycle;
use App\Services\CatalogueManager;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Throwable;

class EditCatalogue extends EditRecord
{
    protected static string $resource = CatalogueResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->requiresConfirmation()->action(function (): void {
            $paths = app(CatalogueFileLifecycle::class)->paths($this->record);
            if (! $this->record->delete()) {
                throw new \RuntimeException('The catalogue could not be deleted.');
            }
            app(CatalogueFileLifecycle::class)->clean($paths);
            $this->redirect(CatalogueResource::getUrl('index'));
        })];
    }

    protected function handleRecordUpdate(Model $record, array $data): Catalogue
    {
        $categoryIds = array_map('intval', $data['category_ids'] ?? []);
        unset($data['category_ids']);
        $oldPaths = app(CatalogueFileLifecycle::class)->paths($record);
        try {
            $saved = app(CatalogueManager::class)->save($data, $categoryIds, $record);
        } catch (Throwable $exception) {
            app(CatalogueFileLifecycle::class)->cleanupNew($oldPaths, Arr::only($data, ['pdf_path', 'cover_path']));
            throw $exception;
        }
        app(CatalogueFileLifecycle::class)->clean([
            'pdf_path' => $oldPaths['pdf_path'] === $saved->pdf_path ? null : $oldPaths['pdf_path'],
            'cover_path' => $oldPaths['cover_path'] === $saved->cover_path ? null : $oldPaths['cover_path'],
        ]);

        return $saved;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['public_id'] = $this->record->public_id;

        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['category_ids'] = $this->record->categories()->pluck('categories.id')->all();

        return $data;
    }
}
