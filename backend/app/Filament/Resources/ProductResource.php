<?php

namespace App\Filament\Resources;

use App\Filament\Components\SeoSearchPreview;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Filament\Resources\ProductResource\RelationManagers\GalleryRelationManager;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Brand;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\ProductSeries;
use App\Models\ProductType;
use App\Models\Supplier;
use App\Services\ProductCardSpecificationsFormatter;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Select as FormSelect;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?string $navigationLabel = 'Products';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Product details')->tabs([
                Tab::make('Основные')->schema([
            Section::make('Product')->schema([
                Select::make('brand_id')->label('Brand')->options(fn (?Product $record): array => Brand::query()
                    ->where(fn (Builder $query) => $query->where('is_active', true)->when($record, fn (Builder $query) => $query->orWhereKey($record->brand_id)))
                    ->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()->required()->live(),
                Select::make('supplier_id')->label('Supplier')->options(fn (?Product $record): array => Supplier::query()
                    ->where(fn (Builder $query) => $query->where('is_active', true)->when($record, fn (Builder $query) => $query->orWhereKey($record->supplier_id)))
                    ->orderBy('name')->pluck('name', 'id')->all())->searchable()->required(),
                Select::make('product_type_id')->label('Product Type')->options(fn (?Product $record): array => ProductType::query()
                    ->where(fn (Builder $query) => $query->where('is_active', true)->when($record, fn (Builder $query) => $query->orWhereKey($record->product_type_id)))
                    ->orderBy('name')->pluck('name', 'id')->all())->searchable()->required()->live()
                    ->helperText(fn (Get $get, ?Product $record): ?string => $record && (int) $get('product_type_id') !== (int) $record->product_type_id
                        ? ($record->attributeValues()->exists()
                            ? 'This product has saved attribute values. Changing Product Type is blocked until those values are explicitly moved or cleared.'
                            : ($record->selectedAttributes()->exists() ? 'This product has selected optional attributes. Remove those selections and save before changing Product Type.' : null)) : null),
                Select::make('series_id')->label('Series')->placeholder('No series')
                    ->options(fn (Get $get): array => ProductSeries::query()
                        ->where('brand_id', $get('brand_id'))
                        ->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()->helperText('Changing Brand does not clear Series. Clear or choose a Series belonging to the selected Brand.'),
                Select::make('category_ids')->label('Categories')->multiple()->searchable()->live()->required(fn (Get $get): bool => $get('status') === 'published')
                    ->options(function (?Product $record, Get $get): array {
                        $selected = array_map('intval', (array) $get('category_ids'));
                        $previouslyAssigned = $record?->categories()->pluck('categories.id')->map(fn ($id): int => (int) $id)->all() ?? [];
                        $allowed = array_values(array_unique([...$selected, ...$previouslyAssigned]));

                        return Category::query()->where(fn (Builder $query) => $query->where('status', 'published')->when($allowed !== [], fn (Builder $query) => $query->orWhereKey($allowed)))
                            ->orderBy('name')->get()->mapWithKeys(fn (Category $category): array => [$category->id => $category->name.($category->status === 'published' ? '' : ' ('.ucfirst($category->status).', existing assignment)')])->all();
                    })
                    ->helperText('Only published categories can be newly assigned. Existing draft or archived assignments remain visible.'),
                Select::make('primary_category_id')->label('Primary Category')->placeholder('No primary category')->nullable()->live()
                    ->options(function (Get $get): array {
                        $selected = array_map('intval', (array) $get('category_ids'));

                        return Category::query()->whereKey($selected)->orderBy('name')->get()->mapWithKeys(fn (Category $category): array => [$category->id => $category->name.($category->status === 'published' ? '' : ' ('.ucfirst($category->status).')')])->all();
                    })
                    ->required(fn (Get $get): bool => $get('status') === 'published')
                    ->helperText('Choose the canonical category from the selected categories. Clear it explicitly for a draft.'),
                TextInput::make('sku')->label('SKU')->required()->maxLength(100)->live(onBlur: true)->dehydrateStateUsing(fn (?string $state): string => trim(strtr((string) $state, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'))),
                TextInput::make('name')->label('Name')->required()->maxLength(255)->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                        if (blank($get('slug'))) {
                            $set('slug', Product::uniqueSlug((string) $state));
                        }
                    })->dehydrateStateUsing(fn (?string $state): string => trim((string) $state)),
                TextInput::make('price_aed')->label('Price (AED)')->numeric()->rule('gt:0')->rule('decimal:0,4')->nullable()->live(onBlur: true)->helperText('Leave blank for Price on enquiry.'),
                Select::make('status')->label('Status')->options(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'])->default('draft')->required()
                    ->helperText('Publication status does not expose this Product on the public site; Product API is not part of this stage.'),
            ])->columns(2),
            Section::make('Product Description')->schema([
                RichEditor::make('description')->label('Description')->toolbarButtons([
                    ['bold', 'italic', 'link'],
                    ['h2', 'h3'],
                    ['bulletList', 'orderedList'],
                ])->fileAttachments(false)->nullable()->columnSpanFull()
                    ->helperText('Supported formatting: paragraphs, H2/H3 headings, lists, bold, italic and inline links.'),
            ])->columnSpanFull(),
                ])->columns(1),
                Tab::make('SEO')->schema([
            Section::make('Search engine listing')->schema([
                TextInput::make('slug')->label('Product slug')->required()->maxLength(255)->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')->unique(ignoreRecord: true)->live(onBlur: true)
                    ->helperText('Globally unique product URL segment. Once set, it is kept when the product name changes.'),
                TextInput::make('seo_title')->label('SEO title override')->maxLength(255)->nullable()->live(onBlur: true)
                    ->helperText('Leave blank to use the product name.'),
                Textarea::make('seo_description')->label('Meta description override')->rows(3)->maxLength(1000)->nullable()->live(onBlur: true)
                    ->helperText('Leave blank until a real product description is available.'),
                Select::make('seo_og_media_asset_id')->label('Open Graph image override')->options(fn (): array => MediaAsset::query()->where('processing_status', 'ready')->get()
                    ->filter(fn (MediaAsset $asset): bool => $asset->variantUrl('full') !== null)
                    ->mapWithKeys(fn (MediaAsset $asset): array => [$asset->id => $asset->public_id])->all())->searchable()->nullable()
                    ->helperText('Leave blank to use the main product image when its media processing is ready.'),
                SeoSearchPreview::make('seo_title', 'seo_description', 'slug', function (string $slug, Get $get): string {
                    $category = Category::query()->find($get('primary_category_id'));
                    if (! $category || blank($slug)) {
                        return 'Canonical URL unavailable until a primary category and product slug are set';
                    }

                    return rtrim((string) config('app.url'), '/').'/shop/'.$category->slug.'/'.$slug;
                }, 'name')->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
                ])->columns(1),
                Tab::make('Attributes')->schema([
            Section::make('Product Attributes')->schema(function (Get $get, ?Product $record): array {
                $typeId = $get('product_type_id');
                if (! $typeId) {
                    return [];
                }

                $assignments = ProductType::query()->whereKey($typeId)
                    ->with(['attributes.options', 'attributes' => fn ($query) => $query->orderByPivot('sort_order')->orderBy('attributes.name')])
                    ->first()?->attributes ?? collect();
                $saved = $record?->attributeValues()->with('selections')->get()->keyBy('attribute_id') ?? collect();
                $previouslySelected = $record?->selectedAttributes()->pluck('attributes.id')->map(fn ($id): int => (int) $id)->all() ?? [];
                $optional = $assignments->filter(fn (Attribute $attribute): bool => ! (bool) $attribute->pivot->is_required
                    && ($attribute->is_active || in_array((int) $attribute->id, $previouslySelected, true) || $saved->has($attribute->id)));
                $selectedIds = array_map('intval', (array) $get('selected_attribute_ids'));

                $components = [];
                if ($optional->isNotEmpty()) {
                    $components[] = CheckboxList::make('selected_attribute_ids')->label('Use optional attributes')
                        ->options($optional->mapWithKeys(fn (Attribute $attribute): array => [$attribute->id => $attribute->name.($attribute->is_active ? '' : ' (inactive, existing assignment)')])->all())
                        ->default($previouslySelected)->live()->columns(2)->columnSpanFull()
                        ->helperText('Selected attributes may be left empty. Clear and save a filled attribute before removing it.');
                }

                $displayable = $assignments->filter(function (Attribute $attribute) use ($selectedIds, $saved): bool {
                    return (bool) $attribute->pivot->is_required || in_array((int) $attribute->id, $selectedIds, true) || $saved->has($attribute->id);
                });
                $components[] = Repeater::make('card_display_attribute_ids')->label('Product card specifications')
                    ->schema([
                        Select::make('attribute_id')->label('Attribute')->options($displayable->mapWithKeys(fn (Attribute $attribute): array => [$attribute->id => $attribute->name])->all())
                            ->required()->disableOptionsWhenSelectedInSiblingRepeaterItems()->live(),
                    ])->defaultItems(0)->reorderable()->addActionLabel('Add card attribute')->columns(1)->columnSpanFull()
                    ->helperText('Choose and reorder the selected attributes shown below the product name. Empty values are omitted.');

                $components[] = Placeholder::make('product_card_preview')->label('Live product card preview')
                    ->content(function (Get $get) use ($record): View {
                        $orderedIds = collect($get('card_display_attribute_ids') ?? [])->map(fn ($row): int => (int) ($row['attribute_id'] ?? 0))->filter()->values()->all();
                        $values = (array) $get('attribute_values');
                        $specifications = app(ProductCardSpecificationsFormatter::class)->format(
                            $get('product_type_id') ? (int) $get('product_type_id') : null,
                            $values,
                            $orderedIds,
                        );
                        $imageUrl = null;
                        if ($record) {
                            $asset = $record->images()->where('is_main', true)->with('mediaAsset')->first()?->mediaAsset;
                            $imageUrl = $asset?->variantUrl('card') ?? $asset?->variantUrl('thumb');
                        }

                        return view('filament.products.card-preview', [
                            'imageUrl' => $imageUrl,
                            'name' => $get('name') ?: 'Product name',
                            'sku' => $get('sku'),
                            'specifications' => $specifications,
                            'price' => $get('price_aed'),
                            'imagePending' => ! $record && filled($get('gallery_uploads')),
                        ]);
                    })->columnSpanFull();

                return [...$components, ...$assignments->filter(function (Attribute $attribute) use ($selectedIds, $saved): bool {
                    return $attribute->is_active || $saved->has($attribute->id)
                        ? ((bool) $attribute->pivot->is_required || in_array((int) $attribute->id, $selectedIds, true) || $saved->has($attribute->id))
                        : false;
                })
                    ->map(function (Attribute $attribute) use ($saved): mixed {
                        $path = "attribute_values.{$attribute->id}";
                        $required = fn (Get $get): bool => (bool) $attribute->pivot->is_required && $get('status') === 'published';
                        $state = $saved->get($attribute->id);
                        $selected = $state?->selections->pluck('attribute_option_id')->map(fn ($id): int => (int) $id)->all() ?? [];

                        if ($attribute->type === 'text') {
                            return TextInput::make($path)->label($attribute->name)->required($required)->maxLength(10000)->live(onBlur: true);
                        }
                        if ($attribute->type === 'number') {
                            return TextInput::make($path)->label($attribute->unit ? "{$attribute->name} ({$attribute->unit})" : $attribute->name)
                                ->numeric()->step('0.000001')->required($required)->live(onBlur: true);
                        }
                        if ($attribute->type === 'boolean') {
                            return FormSelect::make($path)->label($attribute->name)->options(['1' => 'Yes', '0' => 'No'])
                                ->placeholder('Not set')->nullable()->required($required)->live();
                        }

                        $options = $attribute->options->filter(fn (AttributeOption $option): bool => $option->is_active || in_array($option->id, $selected, true))
                            ->mapWithKeys(fn (AttributeOption $option): array => [$option->id => $option->label.($option->is_active ? '' : ' (inactive, existing value)')])->all();
                        $field = FormSelect::make($path)->label($attribute->name)->options($options)->searchable()->required($required);

                        return $attribute->type === 'multiselect' ? $field->multiple()->live() : $field->placeholder('Not set')->nullable()->live();
                    })->values()->all()];
            })->columns(2)->columnSpanFull(),
                ])->columns(1),
                Tab::make('Images')->schema(fn (?Product $record): array => [
            ...($record ? [Livewire::make(GalleryRelationManager::class, [
                'ownerRecord' => $record,
                'pageClass' => EditProduct::class,
                ...GalleryRelationManager::getDefaultProperties(),
            ])->key('product-gallery')] : []),
            Section::make('Product Gallery')->schema([
                Repeater::make('gallery_uploads')->label('Images')->schema([
                    Hidden::make('media_key')->default(fn (): string => (string) Str::uuid()),
                    FileUpload::make('upload')->label('Image')->image()->multiple()->maxFiles(1)->required()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(10240)
                        ->disk('local')->directory('product-originals')->visibility('private')->storeFiles(false)
                        ->imagePreviewHeight('180')->live(),
                    TextInput::make('alt_override')->label('Manual ALT override')->maxLength(255)->nullable()
                        ->helperText('Leave blank to keep the automatic ALT.'),
                    Placeholder::make('automatic_alt')->label('Automatic ALT')->content(function (Get $get, Component $component): string {
                        $name = $get('data.name', true);
                        if (blank($name)) {
                            return 'Enter the product name to preview the automatic ALT.';
                        }

                        $path = explode('.', $component->getStatePath());
                        $currentKey = $path[2] ?? null;
                        $gallery = (array) $get('data.gallery_uploads', true);
                        $position = array_search($currentKey, array_keys($gallery), true);
                        $alt = 'Product image of '.$name;

                        return $position !== false && $position > 0 ? $alt.' '.($position + 1) : $alt;
                    }),
                ])->defaultItems(0)->reorderable()->addActionLabel('Add image')->columns(2)->live(),
                Select::make('main_image_key')->label('Main image')->options(fn (Get $get): array => collect($get('gallery_uploads') ?? [])
                    ->filter(fn (array $image): bool => filled($image['media_key'] ?? null) && filled($image['upload'] ?? null))
                    ->mapWithKeys(function (array $image): array {
                        $upload = $image['upload'];
                        if (is_array($upload)) {
                            $upload = reset($upload);
                        }
                        $name = method_exists($upload, 'getClientOriginalName') ? $upload->getClientOriginalName() : 'Product image';

                        return [$image['media_key'] => $name];
                    })->all())->searchable()->nullable()->live()
                    ->helperText('Choose the main image now. If left blank, the first image becomes main.'),
            ])->visible(fn (?Product $record): bool => $record === null),
                ])->columns(1),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('sku')->label('SKU')->searchable()->sortable(),
            TextColumn::make('name')->label('Name')->searchable()->sortable(),
            TextColumn::make('brand.name')->label('Brand')->sortable()->searchable(),
            TextColumn::make('supplier.name')->label('Supplier')->sortable()->searchable(),
            TextColumn::make('productType.name')->label('Type')->sortable(),
            TextColumn::make('series.name')->label('Series')->placeholder('—'),
            TextColumn::make('price_aed')->label('Price AED')->placeholder('On enquiry')->sortable(),
            TextColumn::make('status')->badge()->sortable(),
            TextColumn::make('updated_at')->label('Updated')->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('brand_id')->label('Brand')->relationship('brand', 'name')->searchable()->preload(),
            SelectFilter::make('supplier_id')->label('Supplier')->relationship('supplier', 'name')->searchable()->preload(),
            SelectFilter::make('product_type_id')->label('Product Type')->relationship('productType', 'name')->searchable()->preload(),
            SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived']),
        ])->defaultSort('updated_at', 'desc')->recordUrl(fn (Product $record) => static::getUrl('edit', ['record' => $record]));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['brand', 'supplier', 'productType', 'series', 'categories', 'primaryCategory', 'attributeValues.selections']);
    }

    public static function getPages(): array
    {
        return ['index' => ListProducts::route('/'), 'create' => CreateProduct::route('/create'), 'edit' => EditProduct::route('/{record}/edit')];
    }

    public static function getRelations(): array
    {
        return [GalleryRelationManager::class];
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
