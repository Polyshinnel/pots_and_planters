<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogueDownloadGrant extends Model
{
    protected $fillable = ['enquiry_id', 'catalogue_id', 'token_hash', 'expires_at', 'revoked_at', 'download_count'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime', 'download_count' => 'integer'];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function catalogue(): BelongsTo
    {
        return $this->belongsTo(Catalogue::class);
    }
}
