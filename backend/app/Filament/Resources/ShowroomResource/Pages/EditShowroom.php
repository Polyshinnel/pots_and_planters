<?php

namespace App\Filament\Resources\ShowroomResource\Pages;

use App\Filament\Resources\ShowroomResource;
use Filament\Resources\Pages\EditRecord;

class EditShowroom extends EditRecord
{
    protected static string $resource = ShowroomResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['phones'] = array_values($data['phones'] ?? []);
        $data['emails'] = array_values(array_map(fn ($item) => is_array($item) ? $item['email'] : $item, $data['emails'] ?? []));

        return $data;
    }
}
