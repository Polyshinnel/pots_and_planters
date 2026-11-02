<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductSeriesResource\Pages\CreateProductSeries;
use App\Filament\Resources\ProductSeriesResource\Pages\EditProductSeries;
use App\Filament\Resources\ProductSeriesResource\Pages\ListProductSeries;
use App\Models\ProductSeries;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProductSeriesResource extends Resource
{
    protected static ?string $model = ProductSeries::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?string $navigationLabel = 'Product Series';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Product Series')->schema([
                Select::make('brand_id')
                    ->label('Brand')
                    ->relationship(
                        name: 'brand',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query, ?ProductSeries $record): Builder => $query
                            ->where(function (Builder $query) use ($record): void {
                                $query->where('is_active', true);

                                if ($record?->brand_id) {
                                    $query->orWhereKey($record->brand_id);
                                }
                            })
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
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
            TextColumn::make('brand.name')->label('Brand')->sortable()->searchable(),
            IconColumn::make('is_active')->label('Active')->boolean()->sortable(),
            TextColumn::make('created_at')->label('Created')->dateTime()->sortable(),
            TextColumn::make('updated_at')->label('Updated')->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('brand_id')->label('Brand')->relationship('brand', 'name')->searchable()->preload(),
            TernaryFilter::make('is_active')->label('Active')->placeholder('All series')->trueLabel('Active')->falseLabel('Inactive'),
        ])->defaultSort('name')->recordUrl(fn (ProductSeries $record) => static::getUrl('edit', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductSeries::route('/'),
            'create' => CreateProductSeries::route('/create'),
            'edit' => EditProductSeries::route('/{record}/edit'),
        ];
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
