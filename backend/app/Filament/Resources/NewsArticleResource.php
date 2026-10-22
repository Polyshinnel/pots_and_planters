<?php

namespace App\Filament\Resources;

use App\Filament\Components\NewsArticlePreview;
use App\Filament\Components\SeoSearchPreview;
use App\Filament\Resources\NewsArticleResource\Pages\CreateNewsArticle;
use App\Filament\Resources\NewsArticleResource\Pages\EditNewsArticle;
use App\Filament\Resources\NewsArticleResource\Pages\ListNewsArticles;
use App\Models\NewsArticle;
use App\Models\NewsCategory;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
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

class NewsArticleResource extends Resource
{
    protected static ?string $model = NewsArticle::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';

    protected static string|\UnitEnum|null $navigationGroup = 'News';

    protected static ?string $navigationLabel = 'Articles';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Article')->schema([
                TextInput::make('title')->required()->maxLength(255)->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                        if (blank($get('slug'))) {
                            $set('slug', static::generateUniqueSlug((string) $state));
                        }
                    }),
                TextInput::make('slug')->required()->maxLength(255)->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')->unique(ignoreRecord: true)->live(onBlur: true)
                    ->helperText('Public URL: /news/{slug}. Changing a slug does not create a redirect.'),
                Select::make('category_id')->label('Category')->options(fn (): array => NewsCategory::query()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all())->searchable()->preload()->required()->live(),
                Select::make('status')->options(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'])->default('draft')->required()->live(),
                DatePicker::make('published_at')->label('Publication date')->native(false)->displayFormat('Y-m-d')->required(fn (Get $get): bool => $get('status') === 'published')->live(),
                Textarea::make('excerpt')->rows(3)->required(fn (Get $get): bool => $get('status') === 'published')->live(onBlur: true)->columnSpanFull(),
                RichEditor::make('body')->label('Article body')->toolbarButtons([
                    ['bold', 'italic', 'link'],
                    ['h2', 'h3'],
                    ['bulletList', 'orderedList'],
                ])->fileAttachments(false)->required(fn (Get $get): bool => $get('status') === 'published')->live(debounce: 500)->columnSpanFull()
                    ->helperText('Supported formatting: paragraphs, H2/H3 headings, lists, bold, italic and inline links.'),
                FileUpload::make('cover_path')->label('Cover image')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(10240)
                    ->disk('public')->directory('news')->visibility('public')->imagePreviewHeight('240')->required(fn (Get $get): bool => $get('status') === 'published')->live(),
                TextInput::make('cover_alt')->label('Cover alt text')->maxLength(255)->required(fn (Get $get): bool => $get('status') === 'published'),
                FileUpload::make('og_image_path')->label('Open Graph image (optional)')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(10240)
                    ->disk('public')->directory('news/og')->visibility('public')->imagePreviewHeight('200'),
                NewsArticlePreview::make()->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
            Section::make('Search engine listing')->schema([
                TextInput::make('seo_title')->label('SEO title')->maxLength(255)->live(onBlur: true)->required(fn (Get $get): bool => $get('status') === 'published'),
                Textarea::make('seo_description')->label('SEO description')->rows(3)->live(onBlur: true)->required(fn (Get $get): bool => $get('status') === 'published'),
                SeoSearchPreview::make('seo_title', 'seo_description', 'slug', fn (string $slug): string => rtrim((string) config('app.url'), '/').'/news/'.($slug ?: '{slug}'))->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->searchable()->sortable()->limit(60),
            TextColumn::make('category.name')->label('Category')->sortable(),
            TextColumn::make('status')->badge()->sortable(),
            TextColumn::make('published_at')->date('Y-m-d')->sortable(),
            TextColumn::make('slug')->fontFamily('mono')->searchable(),
        ])->filters([
            SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived']),
            SelectFilter::make('category')->relationship('category', 'name'),
        ])->defaultSort('published_at', 'desc')->recordUrl(fn (NewsArticle $record) => static::getUrl('edit', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsArticles::route('/'),
            'create' => CreateNewsArticle::route('/create'),
            'edit' => EditNewsArticle::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('category')->orderByDesc('published_at')->orderBy('id');
    }

    public static function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'news';
        $slug = $base;
        $suffix = 2;

        while (NewsArticle::query()->where('slug', $slug)->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
