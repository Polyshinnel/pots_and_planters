<?php

namespace App\Filament\Resources\CatalogueResource\Pages;

use App\Filament\Resources\CatalogueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCatalogues extends ListRecords
{
    protected static string $resource = CatalogueResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
