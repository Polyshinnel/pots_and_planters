<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnquiryItem extends Model
{
    protected $fillable = ['product_identifier', 'sku_snapshot', 'quantity', 'position'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'position' => 'integer'];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }
}
