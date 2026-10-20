<?php

namespace App\Filament\Resources\PageSeoResource\Pages;

use App\Filament\Resources\PageSeoResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditPageSeo extends EditRecord
{
    protected static string $resource = PageSeoResource::class;

    protected Width | string | null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
