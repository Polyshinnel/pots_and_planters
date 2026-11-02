<?php

namespace App\Filament\Resources\AttributeResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class OptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'options';

    protected static ?string $title = 'Options';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return parent::canViewForRecord($ownerRecord, $pageClass)
            && in_array($ownerRecord->type, ['select', 'multiselect'], true);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Option')->schema([
                TextInput::make('label')->label('Label')->required()->maxLength(255)
                    ->dehydrateStateUsing(fn (?string $state): string => trim((string) $state)),
                TextInput::make('value')->label('Value')->required()->maxLength(64)->helperText('Stable key in lowercase snake_case.'),
                TextInput::make('sort_order')->label('Order')->numeric()->integer()->default(0)->required(),
                Toggle::make('is_active')->label('Active')->default(true)->required(),
            ])->columns(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('label')->label('Label')->searchable()->sortable(),
            TextColumn::make('value')->label('Value')->searchable()->sortable(),
            TextColumn::make('sort_order')->label('Order')->sortable(),
            IconColumn::make('is_active')->label('Active')->boolean()->sortable(),
        ])->defaultSort('sort_order')->headerActions([
            CreateAction::make()->visible(fn (): bool => in_array($this->getOwnerRecord()->type, ['select', 'multiselect'], true)),
        ])->recordActions([
            EditAction::make(),
        ]);
    }

    public function isReadOnly(): bool
    {
        return in_array($this->getOwnerRecord()->type, ['select', 'multiselect'], true) === false;
    }
}
