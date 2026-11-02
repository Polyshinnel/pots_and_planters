<?php

namespace App\Filament\Resources\AttributeResource\Pages;

use App\Filament\Resources\AttributeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAttribute extends EditRecord
{
    protected static string $resource = AttributeResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['type'] ?? null) !== 'number') {
            $data['unit'] = null;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->visible(false)];
    }
}
