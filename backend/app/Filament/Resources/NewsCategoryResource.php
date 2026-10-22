<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsCategoryResource\Pages\CreateNewsCategory;
use App\Filament\Resources\NewsCategoryResource\Pages\EditNewsCategory;
use App\Filament\Resources\NewsCategoryResource\Pages\ListNewsCategories;
use App\Models\NewsCategory;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class NewsCategoryResource extends Resource
{
    protected static ?string $model = NewsCategory::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'News';

    protected static ?string $navigationLabel = 'News Categories';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('News category')->schema([
                TextInput::make('name')->required()->maxLength(255)->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                        if (blank($get('slug'))) {
                            $set('slug', static::generateUniqueSlug((string) $state));
                        }
                    }),
                TextInput::make('slug')->required()->maxLength(255)->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')->unique(ignoreRecord: true)
                    ->helperText('URL key for this editorial category.'),
                TextInput::make('sort_order')->numeric()->integer()->minValue(0)->required()->default(0),
                Toggle::make('is_active')->label('Active')->default(true)->required(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('slug')->fontFamily('mono')->searchable(),
            IconColumn::make('is_active')->boolean()->label('Active')->sortable(),
            TextColumn::make('sort_order')->sortable(),
            TextColumn::make('articles_count')->counts('articles')->label('Articles')->sortable(),
        ])->defaultSort('sort_order')->recordUrl(fn (NewsCategory $record) => static::getUrl('edit', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsCategories::route('/'),
            'create' => CreateNewsCategory::route('/create'),
            'edit' => EditNewsCategory::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('articles')->orderBy('sort_order')->orderBy('id');
    }

    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'news-category';
        $slug = $base;
        $suffix = 2;

        while (NewsCategory::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
