<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttributeResource\Pages\CreateAttribute;
use App\Filament\Resources\AttributeResource\Pages\EditAttribute;
use App\Filament\Resources\AttributeResource\Pages\ListAttributes;
use App\Filament\Resources\AttributeResource\RelationManagers\OptionsRelationManager;
use App\Models\Attribute;
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
use Illuminate\Database\Eloquent\Model;

class AttributeResource extends Resource
{
    protected static ?string $model = Attribute::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?string $navigationLabel = 'Attributes';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Attribute')->schema([
                TextInput::make('name')->label('Name')->required()->maxLength(255)->rule('string')
                    ->dehydrateStateUsing(fn (?string $state): string => trim((string) $state)),
                TextInput::make('code')->label('Code')->required()->maxLength(64)->helperText('Stable key in lowercase snake_case.'),
                Select::make('type')->label('Type')->options(array_combine(Attribute::TYPES, Attribute::TYPES))->required()
                    ->disabled(fn (?Attribute $record): bool => $record?->options()->exists() ?? false)
                    ->helperText(fn (?Attribute $record): ?string => $record?->options()->exists() ? 'Type is locked while options exist.' : null),
                TextInput::make('unit')->label('Unit')->default('cm')->required()->maxLength(32)
                    ->helperText('Defaults to cm. Edit the unit when this Attribute uses another measurement.'),
                TextInput::make('short_label')->label('Short label for product cards')->maxLength(32)->nullable()
                    ->helperText('Optional display label, for example a concise unit symbol. It is shown only when explicitly configured.'),
                Toggle::make('is_active')->label('Active')->default(true)->required(),
                Toggle::make('is_filterable')->label('Filterable')->default(false)->required()
                    ->helperText('Available for category filter configuration when this Attribute is assigned to at least one Product Type.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name')->searchable()->sortable(),
            TextColumn::make('code')->label('Code')->searchable()->sortable(),
            TextColumn::make('type')->label('Type')->badge()->sortable(),
            TextColumn::make('unit')->label('Unit')->placeholder('—')->sortable(),
            TextColumn::make('options_count')->counts('options')->label('Options')->sortable(),
            IconColumn::make('is_active')->label('Active')->boolean()->sortable(),
            IconColumn::make('is_filterable')->label('Filterable')->boolean()->sortable(),
            TextColumn::make('created_at')->label('Created')->dateTime()->sortable(),
            TextColumn::make('updated_at')->label('Updated')->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('type')->options(array_combine(Attribute::TYPES, Attribute::TYPES)),
            TernaryFilter::make('is_active')->label('Active')->placeholder('All attributes')->trueLabel('Active')->falseLabel('Inactive'),
            TernaryFilter::make('is_filterable')->label('Filterable')->placeholder('All attributes')->trueLabel('Filterable')->falseLabel('Not filterable'),
        ])->defaultSort('name')->recordUrl(fn (Attribute $record) => static::getUrl('edit', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [OptionsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttributes::route('/'),
            'create' => CreateAttribute::route('/create'),
            'edit' => EditAttribute::route('/{record}/edit'),
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
