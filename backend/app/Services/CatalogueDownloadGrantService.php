<?php

namespace App\Services;

use App\Exceptions\EnquiryResourceUnavailable;
use App\Models\Catalogue;
use App\Models\CatalogueDownloadGrant;
use App\Models\Enquiry;
use Illuminate\Support\Facades\Storage;

class CatalogueDownloadGrantService
{
    /** @return array{token: string, expiresAt: string} */
    public function issueFor(Enquiry $enquiry): array
    {
        $catalogue = Catalogue::query()->whereKey($enquiry->catalogue_id)->where('status', 'published')->lockForUpdate()->first();
        if (! $catalogue || ! $this->safePath($catalogue->pdf_path) || ! Storage::disk('local')->exists($catalogue->pdf_path)) {
            throw new EnquiryResourceUnavailable;
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = now()->addMinutes(15);
        CatalogueDownloadGrant::query()->create([
            'enquiry_id' => $enquiry->id, 'catalogue_id' => $catalogue->id,
            'token_hash' => hash('sha256', $token), 'expires_at' => $expiresAt,
        ]);

        return ['token' => $token, 'expiresAt' => $expiresAt->toIso8601String()];
    }

    public function safePath(?string $path): bool
    {
        return is_string($path) && $path !== '' && preg_match('/^(?!\/)(?![A-Za-z]:)(?!.*(?:^|\/)\.\.(?:\/|$))[A-Za-z0-9._\/-]+$/', $path) === 1;
    }
}
