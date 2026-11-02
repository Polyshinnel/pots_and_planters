<?php

namespace App\Filament\Resources\ProductSeriesResource\Pages;

use App\Filament\Resources\ProductSeriesResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductSeries extends EditRecord
{
    protected static string $resource = ProductSeriesResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->visible(false)];
    }
}
