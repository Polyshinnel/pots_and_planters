<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Product extends Model
{
    protected $fillable = ['brand_id', 'supplier_id', 'product_type_id', 'series_id', 'sku', 'name', 'price_aed', 'status'];

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return ['price_aed' => 'decimal:4', 'primary_category_id' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product): void {
            $product->public_id ??= (string) Str::uuid();
        });

        static::updating(function (Product $product): void {
            if ($product->isDirty('public_id')) {
                throw ValidationException::withMessages(['public_id' => 'The public identifier cannot be changed.']);
            }

            if ($product->isDirty('product_type_id') && $product->attributeValues()->exists()) {
                throw ValidationException::withMessages(['product_type_id' => 'This product has saved attribute values. Move or clear them explicitly before changing Product Type.']);
            }
        });

        static::saving(function (Product $product): void {
            $product->sku = is_string($product->sku) ? trim(strtr($product->sku, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')) : $product->sku;
            $product->name = is_string($product->name) ? trim($product->name) : $product->name;

            $validator = Validator::make($product->getAttributes(), [
                'brand_id' => ['required', 'integer', 'exists:brands,id'],
                'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
                'product_type_id' => ['required', 'integer', 'exists:product_types,id'],
                'series_id' => [
                    'nullable', 'integer',
                    Rule::exists('product_series', 'id')->where('brand_id', $product->brand_id),
                ],
                'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->where('brand_id', $product->brand_id)->ignore($product->getKey())],
                'name' => ['required', 'string', 'max:255'],
                'price_aed' => ['nullable', 'numeric', 'gt:0', 'decimal:0,4', 'max:99999999999.9999'],
                'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $categoryIds = $product->categories()->pluck('categories.id')->all();
            if ($product->primary_category_id !== null && ! in_array((int) $product->primary_category_id, $categoryIds, true)) {
                throw ValidationException::withMessages(['primary_category_id' => 'The primary category must be assigned to this product.']);
            }
            if ($product->status === 'published' && ($categoryIds === [] || $product->primary_category_id === null)) {
                throw ValidationException::withMessages(['primary_category_id' => 'A published product must have at least one category and a primary category.']);
            }
        });
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(ProductSeries::class, 'series_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_category');
    }

    public function primaryCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'primary_category_id');
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }
}
