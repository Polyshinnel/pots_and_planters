<?php

namespace App\Filament\Resources\ShowroomResource\Pages;

use App\Filament\Resources\ShowroomResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShowrooms extends ListRecords
{
    protected static string $resource = ShowroomResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
