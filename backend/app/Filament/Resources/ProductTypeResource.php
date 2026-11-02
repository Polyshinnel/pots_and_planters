<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductTypeResource\Pages\CreateProductType;
use App\Filament\Resources\ProductTypeResource\Pages\EditProductType;
use App\Filament\Resources\ProductTypeResource\Pages\ListProductTypes;
use App\Filament\Resources\ProductTypeResource\RelationManagers\AttributesRelationManager;
use App\Models\ProductType;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProductTypeResource extends Resource
{
    protected static ?string $model = ProductType::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?string $navigationLabel = 'Product Types';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Product Type')->schema([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255)
                    ->rule('string')
                    ->dehydrateStateUsing(fn (?string $state): string => trim((string) $state)),
                Toggle::make('is_active')->label('Active')->default(true)->required(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name')->searchable()->sortable(),
            IconColumn::make('is_active')->label('Active')->boolean()->sortable(),
            TextColumn::make('created_at')->label('Created')->dateTime()->sortable(),
            TextColumn::make('updated_at')->label('Updated')->dateTime()->sortable(),
        ])->filters([
            TernaryFilter::make('is_active')->label('Active')->placeholder('All product types')->trueLabel('Active')->falseLabel('Inactive'),
        ])->defaultSort('name')->recordUrl(fn (ProductType $record) => static::getUrl('edit', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductTypes::route('/'),
            'create' => CreateProductType::route('/create'),
            'edit' => EditProductType::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [AttributesRelationManager::class];
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
