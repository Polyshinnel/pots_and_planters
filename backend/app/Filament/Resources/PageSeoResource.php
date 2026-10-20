<?php

namespace App\Filament\Resources;

use App\Filament\Components\SeoSearchPreview;
use App\Filament\Resources\PageSeoResource\Pages\EditPageSeo;
use App\Filament\Resources\PageSeoResource\Pages\ListPageSeos;
use App\Models\PageSeo;
use App\Support\StaticPageSeo;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PageSeoResource extends Resource
{
    protected static ?string $model = PageSeo::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static string | \UnitEnum | null $navigationGroup = 'SEO';

    protected static ?string $navigationLabel = 'Static Pages';

    protected static ?string $modelLabel = 'static page SEO';

    protected static ?string $pluralModelLabel = 'static page SEO';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('page_key')->disabled(),
            Section::make('Page SEO')->schema([
                TextInput::make('page_label')->label('Page')->formatStateUsing(fn (?string $state, PageSeo $record): string => StaticPageSeo::PAGES[$record->page_key]['label'] ?? $record->page_key)->disabled()->dehydrated(false),
                TextInput::make('title')->label('SEO Title')->required()->maxLength(255)->live(onBlur: true)->helperText(fn (Get $get): string => mb_strlen((string) $get('title')).' characters; recommended around 50–60 characters.'),
                Textarea::make('description')->label('Meta Description')->required()->rows(3)->maxLength(1000)->live(onBlur: true)->helperText(fn (Get $get): string => mb_strlen((string) $get('description')).' characters; recommended around 150–160 characters.'),
                FileUpload::make('og_image_path')->label('Open Graph Image')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(5120)->disk('public')->directory('page-seo')->visibility('public')->imageEditor()->helperText('JPEG, PNG or WebP; up to 5 MB. Recommended size: 1200 × 630 px.'),
                ViewField::make('canonical_preview')->label('Canonical URL')->view('filament.forms.components.canonical-url')->viewData(fn (PageSeo $record): array => ['canonicalUrl' => StaticPageSeo::url($record->page_key) ?? 'https://potsandplanters.ae/'])->dehydrated(false),
                SeoSearchPreview::make('title', 'description', 'page_key'),
                Hidden::make('canonical_url')->default(fn (PageSeo $record): string => StaticPageSeo::url($record->page_key) ?? 'https://potsandplanters.ae/')->dehydrated(false),
            ])->columns(1)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('page_key')->label('Page')->formatStateUsing(fn (string $state): string => StaticPageSeo::PAGES[$state]['label'] ?? $state)->searchable(),
            TextColumn::make('page_key')->label('URL')->formatStateUsing(fn (string $state): string => StaticPageSeo::path($state) ?? '/')->fontFamily('mono'),
            TextColumn::make('title')->label('SEO Title')->wrap()->limit(70),
            TextColumn::make('updated_at')->label('Updated')->dateTime()->sortable(),
        ])->recordUrl(fn (PageSeo $record): string => static::getUrl('edit', ['record' => $record]))->defaultSort('page_key');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPageSeos::route('/'),
            'edit' => EditPageSeo::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('page_key', array_keys(StaticPageSeo::PAGES));
    }

    public static function canCreate(): bool { return false; }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return false; }

    public static function canDeleteAny(): bool { return false; }
}
