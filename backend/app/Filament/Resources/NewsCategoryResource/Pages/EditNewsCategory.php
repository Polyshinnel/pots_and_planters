<?php

namespace App\Filament\Resources\NewsCategoryResource\Pages;

use App\Filament\Resources\NewsCategoryResource;
use App\Models\NewsCategory;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditNewsCategory extends EditRecord
{
    protected static string $resource = NewsCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->requiresConfirmation()->action(function (): void {
            /** @var NewsCategory $record */
            $record = $this->record;
            if ($record->articles()->exists()) {
                Notification::make()->danger()->title('Category has articles')->body('Move or remove its articles before deleting this category.')->send();

                return;
            }

            $record->delete();
            $this->redirect(NewsCategoryResource::getUrl('index'));
        })];
    }
}
