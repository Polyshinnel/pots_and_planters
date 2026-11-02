<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Services\ProductCategoryManager;
use App\Services\ProductGalleryManager;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $galleryUploads = $data['gallery_uploads'] ?? [];
        $mainImageKey = $data['main_image_key'] ?? null;
        unset($data['gallery_uploads'], $data['main_image_key']);

        try {
            $product = app(ProductCategoryManager::class)->save($data);

            if ($galleryUploads !== []) {
                try {
                    app(ProductGalleryManager::class)->uploadMany($product, $galleryUploads, $mainImageKey);
                } catch (\Throwable $exception) {
                    report($exception);
                    Notification::make()->danger()->title('Product created; some images could not be attached')
                        ->body('Open the product edit page to retry adding images. No partial gallery links were saved.')
                        ->persistent()->send();
                }
            }

            return $product;
        } catch (QueryException $exception) {
            $driverCode = $exception->errorInfo[1] ?? null;
            if ($driverCode === 1062) {
                throw ValidationException::withMessages(['data.sku' => 'This SKU is already used by this Brand.']);
            }
            if (in_array($driverCode, [1205, 1213, 1451, 1452], true)) {
                throw ValidationException::withMessages(['data.category_ids' => 'The product could not be saved because a selected category changed. Review the category selections and try again.']);
            }

            throw $exception;
        }
    }

    protected function getRedirectUrl(): string
    {
        return ProductResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
