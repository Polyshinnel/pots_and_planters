<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductCategoryManager;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->visible(false)];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(ProductCategoryManager::class)->save($data, $record instanceof Product ? $record : null);
        } catch (QueryException $exception) {
            $driverCode = $exception->errorInfo[1] ?? null;
            if ($driverCode === 1062) {
                if (str_contains($exception->getMessage(), 'products_slug_unique')) {
                    throw ValidationException::withMessages(['data.slug' => 'This product slug is already used.']);
                }
                if (str_contains($exception->getMessage(), 'product_url_aliases_path_unique')) {
                    throw ValidationException::withMessages(['data.slug' => 'This product URL conflicts with reserved URL history.']);
                }
                throw ValidationException::withMessages(['data.sku' => 'This SKU is already used by this Brand.']);
            }
            if (in_array($driverCode, [1205, 1213, 1451, 1452], true)) {
                throw ValidationException::withMessages(['data.category_ids' => 'The product could not be saved because a selected category changed. Review the category selections and try again.']);
            }

            throw $exception;
        }
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['slug'] = $this->record->slug ?: Product::uniqueSlug($this->record->name, (int) $this->record->getKey());
        $data['category_ids'] = $this->record->categories()->pluck('categories.id')->map(fn ($id): int => (int) $id)->all();
        $data['attribute_values'] = $this->record->attributeValues()->with(['attribute', 'selections'])->get()->mapWithKeys(function ($value): array {
            $attribute = $value->attribute;
            $state = match ($attribute->type) {
                'text' => $value->text_value,
                'number' => $value->number_value,
                'boolean' => $value->boolean_value === null ? null : ($value->boolean_value ? '1' : '0'),
                'select' => $value->selections->first()?->attribute_option_id,
                'multiselect' => $value->selections->pluck('attribute_option_id')->map(fn ($id): int => (int) $id)->all(),
            };

            return [$value->attribute_id => $state];
        })->all();

        return $data;
    }
}
