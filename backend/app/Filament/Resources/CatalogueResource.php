<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CatalogueResource\Pages\CreateCatalogue;
use App\Filament\Resources\CatalogueResource\Pages\EditCatalogue;
use App\Filament\Resources\CatalogueResource\Pages\ListCatalogues;
use App\Models\Catalogue;
use App\Models\Category;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CatalogueResource extends Resource
{
    protected static ?string $model = Catalogue::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?string $navigationLabel = 'PDF Catalogues';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Catalogue')->schema([
                TextInput::make('title')->required()->maxLength(255)->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                        if (blank($get('slug'))) {
                            $set('slug', static::generateUniqueSlug((string) $state));
                        }
                    }),
                TextInput::make('slug')->required()->maxLength(255)->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')->unique(ignoreRecord: true),
                Textarea::make('description')->rows(3)->columnSpanFull(),
                Select::make('category_ids')->label('Categories')->multiple()->options(fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->all())->preload()->searchable()->required(fn ($get): bool => $get('status') === 'published')->dehydrated(),
                Select::make('status')->options(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'])->default('draft')->required()->live(),
                TextInput::make('sort_order')->numeric()->integer()->minValue(0)->required()->default(0),
                FileUpload::make('pdf_path')->label('Private PDF')->disk('local')->directory('catalogues')->visibility('private')->acceptedFileTypes(['application/pdf'])->rules(['mimetypes:application/pdf'])->maxSize(204800)->downloadable(false)->openable(false)->deletable(false)->required(fn ($get): bool => $get('status') === 'published')->helperText('PDF stored privately. Maximum 200 MB.'),
                FileUpload::make('cover_path')->label('Public cover')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(2048)->disk('public')->directory('catalogue-covers')->visibility('public')->imageEditor()->imagePreviewHeight('240')->deletable(false)->required(fn ($get): bool => $get('status') === 'published'),
                TextInput::make('cover_alt')->label('Cover alt text')->maxLength(255),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->searchable()->sortable(),
            TextColumn::make('slug')->fontFamily('mono')->searchable(),
            TextColumn::make('status')->badge(),
            TextColumn::make('categories.name')->label('Categories')->badge(),
            TextColumn::make('sort_order')->sortable(),
            TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived']),
            SelectFilter::make('categories')->relationship('categories', 'name')->multiple(),
        ])->defaultSort('sort_order')->recordUrl(fn (Catalogue $record) => static::getUrl('edit', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return ['index' => ListCatalogues::route('/'), 'create' => CreateCatalogue::route('/create'), 'edit' => EditCatalogue::route('/{record}/edit')];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('categories')->orderBy('sort_order')->orderBy('id');
    }

    public static function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'catalogue';
        $slug = $base;
        $suffix = 2;

        while (Catalogue::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
