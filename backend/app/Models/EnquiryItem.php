<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnquiryItem extends Model
{
    protected $fillable = [
        'product_identifier', 'product_public_id', 'brand_public_id', 'sku_snapshot', 'brand_name_snapshot',
        'quantity', 'unit_price_aed', 'currency', 'availability_snapshot', 'snapshot_at', 'product_snapshot', 'position',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer', 'position' => 'integer', 'unit_price_aed' => 'decimal:4',
            'availability_snapshot' => 'boolean', 'snapshot_at' => 'immutable_datetime', 'product_snapshot' => 'array',
        ];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }
}
