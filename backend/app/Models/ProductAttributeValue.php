<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductAttributeValue extends Model
{
    protected $fillable = ['product_id', 'product_type_id', 'attribute_id', 'text_value', 'number_value', 'boolean_value'];

    protected function casts(): array
    {
        return ['number_value' => 'decimal:6', 'boolean_value' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function selections(): HasMany
    {
        return $this->hasMany(ProductAttributeSelection::class);
    }
}
