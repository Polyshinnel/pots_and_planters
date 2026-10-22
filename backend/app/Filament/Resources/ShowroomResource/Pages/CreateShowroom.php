<?php

namespace App\Filament\Resources\ShowroomResource\Pages;

use App\Filament\Resources\ShowroomResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShowroom extends CreateRecord
{
    protected static string $resource = ShowroomResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['public_id'] = $data['public_id'] ?? ShowroomResource::generatePublicId((string) ($data['name'] ?? 'showroom'));
        $data['phones'] = array_values($data['phones'] ?? []);
        $data['emails'] = array_values(array_map(fn ($item) => is_array($item) ? $item['email'] : $item, $data['emails'] ?? []));

        return $data;
    }
}
