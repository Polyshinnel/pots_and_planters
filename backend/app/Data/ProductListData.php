<?php

namespace App\Data;

use App\Models\Product;
use App\Services\ProductCardSpecificationsFormatter;
use App\Services\ProductUrlResolver;
use Illuminate\Support\Facades\Storage;

final readonly class ProductListData
{
    public function __construct(private Product $product) {}

    public function toArray(): array
    {
        $product = $this->product;
        $values = $product->attributeValues->keyBy('attribute_id')->map(fn ($value) => match (true) {
            $value->boolean_value !== null => (bool) $value->boolean_value,
            $value->number_value !== null => (string) $value->number_value,
            $value->text_value !== null => (string) $value->text_value,
            default => null,
        })->all();
        foreach ($product->attributeValues as $value) {
            if ($value->selections->isNotEmpty()) {
                $values[$value->attribute_id] = $value->selections->sortBy('id')->map(fn ($selection) => $selection->option?->id)->filter()->all();
            }
        }
        $formattedText = app(ProductCardSpecificationsFormatter::class)->format($product->product_type_id, $values, array_map('intval', $product->card_display_attribute_ids ?? []), $product->productType);
        $specifications = $this->specifications($product, $values);
        $main = $product->images->first(fn ($image) => $image->is_main && $this->variant($image->mediaAsset, 'card', 'thumb'));
        $imageUrl = $main ? $this->variant($main->mediaAsset, 'card', 'thumb') : null;

        return [
            'id' => (string) $product->public_id,
            'slug' => (string) $product->slug,
            'sku' => (string) $product->sku,
            'name' => (string) $product->name,
            'brand' => ['id' => (string) $product->brand->public_id, 'name' => (string) $product->brand->name],
            'priceAed' => $product->price_aed === null ? null : (string) $product->price_aed,
            'currency' => 'AED',
            'categoryIds' => $product->categories->map(fn ($category) => (string) $category->public_id)->values()->all(),
            'primaryCategory' => ['id' => (string) $product->primaryCategory->public_id, 'slug' => $product->primaryCategory->slug, 'name' => $product->primaryCategory->name, 'path' => '/shop/'.$product->primaryCategory->slug],
            'canonicalPath' => app(ProductUrlResolver::class)->canonicalPath($product),
            'cardImage' => $imageUrl ? ['url' => $imageUrl, 'alt' => $this->alt($main)] : null,
            'cardSpecifications' => $specifications,
            'cardSpecificationsText' => $formattedText,
        ];
    }

    /** @return list<array{code:string,label:string,value:string,displayText:string}> */
    private function specifications(Product $product, array $values): array
    {
        $attributes = $product->productType->attributes->keyBy('id');
        $result = [];
        foreach (array_map('intval', $product->card_display_attribute_ids ?? []) as $id) {
            $attribute = $attributes->get($id);
            if (! $attribute) {
                continue;
            }
            $value = $values[$id] ?? null;
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $label = $attribute->short_label ?: $attribute->name;
            if ($attribute->type === 'boolean') {
                $value = $value ? 'true' : 'false';
            } elseif (in_array($attribute->type, ['select', 'multiselect'], true)) {
                $ids = is_array($value) ? $value : [$value];
                $value = $attribute->options->whereIn('id', $ids)->sortBy(fn ($option) => array_search($option->id, $ids, true))->pluck('label')->implode(', ');
            }
            $value = (string) $value;
            $display = $attribute->type === 'number' && filled($attribute->unit) ? $value.' '.$attribute->unit : $value;
            $result[] = (new ProductSpecificationData($attribute->code, $label, $value, $display))->toArray();
        }

        return $result;
    }

    private function variant($asset, string ...$names): ?string
    {
        if (! $asset || $asset->processing_status !== 'ready') {
            return null;
        }
        foreach ($names as $name) {
            if (isset($asset->variants[$name]['path']) && Storage::disk('public')->exists($asset->variants[$name]['path'])) {
                return Storage::disk('public')->url($asset->variants[$name]['path']);
            }
        }

        return null;
    }

    private function alt($image): string
    {
        if (filled($image->alt_override)) {
            return $image->alt_override;
        }

        $position = $this->product->images->search(fn ($candidate) => $candidate->is($image));
        $alt = 'Product image of '.$this->product->name;

        return $position !== false && $position > 0 ? $alt.' '.($position + 1) : $alt;
    }
}
