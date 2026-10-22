<?php

namespace App\Filament\Resources\SiteContactSettingResource\Pages;

use App\Filament\Resources\SiteContactSettingResource;
use Filament\Resources\Pages\EditRecord;

class EditSiteContactSettings extends EditRecord
{
    protected static string $resource = SiteContactSettingResource::class;

    public function mount(int|string $record = '1'): void
    {
        parent::mount('1');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
