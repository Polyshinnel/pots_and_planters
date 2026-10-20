<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;
    protected function getHeaderActions(): array { return [DeleteAction::make()->visible(fn () => $this->record->status !== 'published')]; }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['public_id'] = $this->record->public_id;
        $data['slug'] = $this->record->slug;

        return $data;
    }
}
