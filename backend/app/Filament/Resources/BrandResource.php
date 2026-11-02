<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BrandResource\Pages\CreateBrand;
use App\Filament\Resources\BrandResource\Pages\EditBrand;
use App\Filament\Resources\BrandResource\Pages\ListBrands;
use App\Models\Brand;
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

class BrandResource extends Resource
{
    protected static ?string $model = Brand::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?string $navigationLabel = 'Brands';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Brand')->schema([
                TextInput::make('name')->label('Name')->required()->maxLength(255)->rule('string')->dehydrateStateUsing(fn (?string $state): string => trim((string) $state)),
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
            TernaryFilter::make('is_active')->label('Active')->placeholder('All brands')->trueLabel('Active')->falseLabel('Inactive'),
        ])->defaultSort('name')->recordUrl(fn (Brand $record) => static::getUrl('edit', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return ['index' => ListBrands::route('/'), 'create' => CreateBrand::route('/create'), 'edit' => EditBrand::route('/{record}/edit')];
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
