<?php

namespace App\Filament\Resources;

use App\Filament\Components\SeoSearchPreview;
use App\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Models\Category;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static string | \UnitEnum | null $navigationGroup = 'Catalogue';
    protected static ?string $navigationLabel = 'Categories';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Category')->schema([
                TextInput::make('public_id')->label('Stable public ID')->required()->maxLength(64)->unique(ignoreRecord: true)->disabled(fn (?Category $record) => $record !== null)->dehydrated()->helperText('Stable product mapping key. Keep this value when changing the URL slug.'),
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('slug')->required()->maxLength(255)->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')->unique(ignoreRecord: true)->live(onBlur: true)
                    ->disabled(fn (?Category $record) => $record !== null)->dehydrated()->helperText(fn (?Category $record) => $record ? 'Category slugs are locked after creation to protect existing URLs.' : 'URL: /shop/{slug}'),
                TextInput::make('heading')->label('H1')->required()->maxLength(255),
                Textarea::make('tile_description')->label('Category tile description')->required()->rows(2),
                Textarea::make('description')->label('Category page intro')->required()->rows(3),
                TextInput::make('intro_heading')->label('Long description heading')->maxLength(255),
                Repeater::make('intro_paragraphs')->label('Long description')->simple(Textarea::make('paragraph')->required())->default([])->columnSpanFull(),
                FileUpload::make('image_path')->label('Category image')->required(fn (Get $get): bool => $get('status') === 'published')->image()->acceptedFileTypes(['image/jpeg','image/png','image/webp'])->maxSize(5120)->disk('public')->directory('categories')->visibility('public')->imageEditor()->imagePreviewHeight('240'),
                TextInput::make('image_alt')->label('Image alt text')->required(fn (Get $get): bool => $get('status') === 'published')->maxLength(255),
                Select::make('status')->options(['draft'=>'Draft','published'=>'Published','archived'=>'Archived'])->required()->default('draft')->live(),
                TextInput::make('sort_order')->numeric()->integer()->minValue(0)->required()->default(0),
            ])->columns(2)->columnSpanFull(),
            Section::make('SEO')->schema([
                TextInput::make('seo_title')->label('SEO Title')->required()->maxLength(255)->live(onBlur: true)->helperText(fn (Get $get) => mb_strlen((string) $get('seo_title')).' characters; recommended around 50–60 characters.'),
                Textarea::make('seo_description')->label('Meta Description')->required()->rows(3)->maxLength(1000)->live(onBlur: true),
                FileUpload::make('og_image_path')->label('Open Graph Image')->image()->acceptedFileTypes(['image/jpeg','image/png','image/webp'])->maxSize(5120)->disk('public')->directory('category-og')->visibility('public')->imageEditor(),
                SeoSearchPreview::make('seo_title', 'seo_description', 'slug', fn (string $slug) => rtrim((string) config('app.url'), '/').'/shop/'.($slug ?: '{slug}')),
            ])->columns(1)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(), TextColumn::make('slug')->fontFamily('mono')->searchable(),
            TextColumn::make('status')->badge(), TextColumn::make('sort_order')->sortable(), TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->defaultSort('sort_order')->reorderable('sort_order')->recordUrl(fn (Category $record) => static::getUrl('edit', ['record' => $record]));
    }

    public static function getPages(): array { return ['index'=>ListCategories::route('/'),'create'=>CreateCategory::route('/create'),'edit'=>EditCategory::route('/{record}/edit')]; }
    public static function getEloquentQuery(): Builder { return parent::getEloquentQuery()->orderBy('sort_order')->orderBy('id'); }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return $record->status !== 'published'; }
    public static function canDeleteAny(): bool { return false; }
}
