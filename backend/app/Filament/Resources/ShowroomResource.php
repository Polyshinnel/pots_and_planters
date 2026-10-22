<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShowroomResource\Pages\CreateShowroom;
use App\Filament\Resources\ShowroomResource\Pages\EditShowroom;
use App\Filament\Resources\ShowroomResource\Pages\ListShowrooms;
use App\Models\Showroom;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ShowroomResource extends Resource
{
    protected static ?string $model = Showroom::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static string|\UnitEnum|null $navigationGroup = 'Contacts';

    protected static ?string $navigationLabel = 'Showrooms';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Showroom')->schema([
                TextInput::make('public_id')->label('Stable public ID')->required()->maxLength(255)->unique(ignoreRecord: true)
                    ->helperText('Stable public identifier used by API consumers.'),
                TextInput::make('name')->required(fn (Get $get): bool => $get('status') === 'published')->maxLength(255),
                Select::make('status')->options(['draft' => 'Draft', 'published' => 'Published'])->default('draft')->required()->live(),
                Textarea::make('description')->rows(3)->columnSpanFull(),
                Textarea::make('address')->required(fn (Get $get): bool => $get('status') === 'published')->columnSpanFull(),
                TextInput::make('city')->required(fn (Get $get): bool => $get('status') === 'published')->maxLength(255),
                TextInput::make('country')->required(fn (Get $get): bool => $get('status') === 'published')->maxLength(255),
                Repeater::make('phones')->label('Phone numbers')->schema([
                    TextInput::make('label')->label('Displayed label')->required()->maxLength(255),
                    TextInput::make('href')->label('Telephone link (href)')->required()->regex('/^tel:\\+?[0-9(). -]+$/')->maxLength(2048),
                ])->columns(2)->default([])->reorderable()->columnSpanFull(),
                Repeater::make('emails')->label('Email addresses')->simple(TextInput::make('email')->email()->required()->maxLength(255))->default([])->reorderable()->columnSpanFull(),
                TextInput::make('instagram_handle')->maxLength(255),
                TextInput::make('instagram_url')->url()->maxLength(2048),
                TextInput::make('sort_order')->numeric()->integer()->minValue(0)->required()->default(0),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('public_id')->label('Public ID')->fontFamily('mono')->searchable(),
            TextColumn::make('status')->badge()->sortable(),
            TextColumn::make('sort_order')->sortable(),
        ])->defaultSort('sort_order')->recordUrl(fn (Showroom $record) => static::getUrl('edit', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return ['index' => ListShowrooms::route('/'), 'create' => CreateShowroom::route('/create'), 'edit' => EditShowroom::route('/{record}/edit')];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('sort_order')->orderBy('id');
    }

    public static function generatePublicId(string $name): string
    {
        $base = Str::slug($name) ?: 'showroom';
        $candidate = $base;
        $suffix = 2;
        while (Showroom::query()->where('public_id', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }
}
