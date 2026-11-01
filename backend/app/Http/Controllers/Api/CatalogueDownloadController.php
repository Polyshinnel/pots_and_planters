<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CatalogueDownloadGrant;
use App\Services\CatalogueDownloadGrantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogueDownloadController extends Controller
{
    public function __invoke(Request $request, CatalogueDownloadGrantService $grantService): StreamedResponse|JsonResponse
    {
        $token = $request->bearerToken();
        $enquiryId = (string) $request->header('X-Catalogue-Enquiry', '');
        if (! $token || strlen($token) !== 64 || ! preg_match('/^[a-f0-9]{64}$/', $token) || ! preg_match('/^[0-9a-f-]{36}$/i', $enquiryId)) {
            return $this->error(404, 'download_grant_invalid', 'The download is unavailable.');
        }

        $resolved = DB::transaction(function () use ($token, $enquiryId, $grantService): array {
            $grant = CatalogueDownloadGrant::query()->where('token_hash', hash('sha256', $token))
                ->whereHas('enquiry', fn ($query) => $query->where('public_id', $enquiryId))
                ->lockForUpdate()->first();
            if (! $grant || $grant->revoked_at) {
                return ['error' => 'invalid'];
            }
            if ($grant->expires_at->isPast()) {
                return ['error' => 'expired'];
            }
            $catalogue = $grant->catalogue;
            if (! $catalogue || $catalogue->status !== 'published' || ! $grantService->safePath($catalogue->pdf_path) || ! Storage::disk('local')->exists($catalogue->pdf_path)) {
                return ['error' => 'unavailable'];
            }
            $stream = Storage::disk('local')->readStream($catalogue->pdf_path);
            if (! is_resource($stream)) {
                return ['error' => 'unavailable'];
            }
            $grant->increment('download_count');

            return ['stream' => $stream, 'filename' => preg_replace('/[^A-Za-z0-9._-]+/', '-', $catalogue->slug).'.pdf'];
        });

        if (isset($resolved['error'])) {
            return match ($resolved['error']) {
                'expired' => $this->error(410, 'download_grant_expired', 'The download access has expired. Submit a new catalogue request to continue.'),
                default => $this->error(404, 'download_unavailable', 'The download is unavailable.'),
            };
        }

        $response = response()->streamDownload(function () use ($resolved): void {
            try {
                fpassthru($resolved['stream']);
            } finally {
                fclose($resolved['stream']);
            }
        }, $resolved['filename'], ['Content-Type' => 'application/pdf']);
        $response->headers->set('Cache-Control', 'private, no-store, no-cache, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    private function error(int $status, string $code, string $message): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message, 'requestId' => (string) str()->uuid()]], $status)
            ->header('Cache-Control', 'no-store, private');
    }
}
