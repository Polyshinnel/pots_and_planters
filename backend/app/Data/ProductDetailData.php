<?php

namespace App\Data;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;

final readonly class ProductDetailData
{
    public function __construct(private Product $product) {}

    public function toArray(): array
    {
        $base = (new ProductListData($this->product))->toArray();
        $product = $this->product;
        $attributes = [];
        foreach ($product->attributeValues as $row) {
            $attribute = $row->attribute;
            if (! $attribute) {
                continue;
            }
            $value = match ($attribute->type) {
                'text' => $row->text_value === null || $row->text_value === '' ? null : (string) $row->text_value,
                'number' => $row->number_value === null ? null : (string) $row->number_value,
                'boolean' => $row->boolean_value === null ? null : (bool) $row->boolean_value,
                'select' => $row->selections->first()?->option?->value,
                'multiselect' => $row->selections->sortBy('id')->map(fn ($selection) => $selection->option?->value)->filter()->values()->all(),
            };
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $attributes[] = [
                'id' => (string) $attribute->public_id,
                'code' => (string) $attribute->code,
                'label' => (string) $attribute->name,
                'shortLabel' => $attribute->short_label,
                'type' => $attribute->type,
                'value' => $value,
                'unit' => $attribute->type === 'number' ? $attribute->unit : null,
                'options' => in_array($attribute->type, ['select', 'multiselect'], true)
                    ? $row->selections->sortBy('id')->map(fn ($selection) => ['value' => (string) $selection->option?->value, 'label' => (string) $selection->option?->label])->values()->all()
                    : [],
            ];
        }
        $images = [];
        foreach ($product->images as $image) {
            $asset = $image->mediaAsset;
            if (! $asset || $asset->processing_status !== 'ready') {
                continue;
            }
            $url = $this->variant($asset, 'full', 'card', 'thumb');
            if (! $url) {
                continue;
            }
            $images[] = ['url' => $url, 'cardUrl' => $this->variant($asset, 'card'), 'thumbUrl' => $this->variant($asset, 'thumb'), 'alt' => $this->alt($image), 'order' => (int) $image->sort_order, 'isMain' => (bool) $image->is_main];
        }
        $og = $product->seoOgMediaAsset;
        if (! $og || ! ($ogUrl = $this->variant($og, 'full', 'card', 'thumb'))) {
            $main = $product->images->first(fn ($image) => $image->is_main && $image->mediaAsset?->processing_status === 'ready');
            $ogUrl = $main ? $this->variant($main->mediaAsset, 'full', 'card', 'thumb') : null;
        }

        return $base + [
            'series' => $product->series ? ['id' => (string) $product->series->public_id, 'name' => (string) $product->series->name] : null,
            'descriptionHtml' => $product->description,
            'seo' => ['title' => $product->effective_seo_title, 'description' => $product->effective_seo_description, 'ogImage' => $ogUrl, 'robots' => 'noindex,follow'],
            'images' => $images,
            'attributes' => $attributes,
        ];
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
