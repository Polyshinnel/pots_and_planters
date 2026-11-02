<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\MediaAsset;
use App\Models\ProductImage;
use App\Services\MediaAssetDispatcher;
use App\Services\ProductGalleryManager;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class GalleryRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'Product Gallery';

    protected static ?string $modelLabel = 'image';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('alt_override')->label('Manual ALT override')->maxLength(255)->nullable()
                ->helperText('Leave blank to use an automatically generated ALT from the product name and image position.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            ImageColumn::make('preview')->label('Preview')->state(fn (ProductImage $record): ?string => $record->mediaAsset->variantUrl())->height(90),
            TextColumn::make('mediaAsset.processing_status')->label('Processing')->badge()
                ->color(fn (string $state): string => match ($state) {
                    'ready' => 'success', 'failed' => 'danger', 'processing' => 'warning', default => 'gray',
                }),
            TextColumn::make('mediaAsset.processing_error')->label('Processing error')->wrap()->placeholder('—'),
            IconColumn::make('is_main')->label('Main')->boolean(),
            TextColumn::make('effective_alt')->label('Effective ALT')->state(fn (ProductImage $record): string => $record->effective_alt)->wrap(),
            TextColumn::make('alt_override')->label('Manual ALT')->placeholder('Automatic')->toggleable(),
            TextColumn::make('sort_order')->label('Order')->sortable(),
        ])->poll('5s')->defaultSort('sort_order')->reorderable('sort_order')->headerActions([
            CreateAction::make()->label('Add image')->schema([
                Select::make('media_asset_id')->label('Reuse existing media')->relationship(
                    name: 'mediaAsset',
                    titleAttribute: 'public_id',
                    modifyQueryUsing: fn (Builder $query): Builder => $query->whereHas('productImages', fn (Builder $images): Builder => $images->where('product_id', '!=', $this->getOwnerRecord()->getKey())),
                )->searchable()->nullable()->helperText('Select a registered file to reuse it without uploading another copy.'),
                FileUpload::make('upload')->label('Upload new image')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(10240)->disk('local')->directory('product-originals')->visibility('private')->storeFiles(false)
                    ->imagePreviewHeight('220')->nullable(),
                TextInput::make('alt_override')->label('Manual ALT override')->maxLength(255)->nullable(),
            ])->using(function (array $data): ProductImage {
                if (filled($data['media_asset_id'] ?? null) === filled($data['upload'] ?? null)) {
                    throw ValidationException::withMessages(['media_asset_id' => 'Choose an existing media file or upload a new image.']);
                }

                $manager = app(ProductGalleryManager::class);
                $product = $this->getOwnerRecord();
                $image = filled($data['upload'] ?? null)
                    ? $manager->upload($product, $this->uploadedFile($data['upload']))
                    : $manager->attach($product, MediaAsset::query()->findOrFail($data['media_asset_id']));

                if (filled($data['alt_override'] ?? null)) {
                    $manager->updateAlt($image, $data['alt_override']);
                }

                return $image;
            }),
        ])->recordActions([
            Action::make('retryProcessing')->label('Retry processing')->icon('heroicon-o-arrow-path')
                ->visible(fn (ProductImage $record): bool => auth()->user()?->is_admin === true && $record->mediaAsset->canRetryProcessing())
                ->action(function (ProductImage $record): void {
                    abort_unless(auth()->user()?->is_admin === true, 403);
                    $requested = app(MediaAssetDispatcher::class)->request($record->mediaAsset);
                    $asset = $record->mediaAsset->fresh();
                    Notification::make()->title($requested && $asset->queued_at ? 'Image queued for processing' : 'Review the current processing status')
                        ->body($asset->processing_error ?? 'An active request already exists, or the image is ready.')->send();
                }),
            Action::make('makeMain')->label('Make main')->icon('heroicon-o-star')->visible(fn (ProductImage $record): bool => ! $record->is_main)
                ->action(fn (ProductImage $record) => app(ProductGalleryManager::class)->setMain($record)),
            EditAction::make()->label('Edit ALT'),
            Action::make('detach')->label('Detach')->color('danger')->requiresConfirmation()->modalHeading('Detach image from this product?')
                ->modalDescription('The shared media file will remain stored and can still be used by other products.')
                ->action(fn (ProductImage $record) => app(ProductGalleryManager::class)->detach($record)),
        ]);
    }

    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        if (! $this->getTable()->isReorderable()) {
            return;
        }

        app(ProductGalleryManager::class)->reorder($this->getOwnerRecord(), array_map('intval', $order));
    }

    private function uploadedFile(mixed $file): UploadedFile
    {
        if ($file instanceof TemporaryUploadedFile) {
            return $file;
        }

        if ($file instanceof UploadedFile) {
            return $file;
        }

        throw ValidationException::withMessages(['upload' => 'Upload a valid JPEG, PNG, or WebP image.']);
    }
}
