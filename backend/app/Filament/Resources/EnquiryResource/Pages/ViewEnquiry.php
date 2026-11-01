<?php

namespace App\Filament\Resources\EnquiryResource\Pages;

use App\Filament\Resources\EnquiryResource;
use App\Services\EnquiryWorkflow;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewEnquiry extends ViewRecord
{
    protected static string $resource = EnquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('changeStatus')->label('Change status')->icon('heroicon-o-arrow-path')->form([
                Select::make('status')->options(['new' => 'New', 'in_progress' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'])->required()->default(fn () => $this->record->status),
            ])->action(function (array $data, EnquiryWorkflow $workflow): void {
                $workflow->changeStatus($this->record, $data['status'], (int) auth()->id());
                Notification::make()->title('Enquiry status updated')->success()->send();
                $this->refreshFormData(['status']);
            }),
            Action::make('addNote')->label('Add internal note')->icon('heroicon-o-pencil-square')->form([
                Textarea::make('note')->required()->maxLength(5000)->rows(5),
            ])->action(function (array $data, EnquiryWorkflow $workflow): void {
                $workflow->addNote($this->record, $data['note'], (int) auth()->id());
                Notification::make()->title('Internal note added')->success()->send();
            }),
        ];
    }
}
