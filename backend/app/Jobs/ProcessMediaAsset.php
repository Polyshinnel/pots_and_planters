<?php

namespace App\Jobs;

use App\Exceptions\MediaProcessingException;
use App\Models\MediaAsset;
use App\Services\MediaAssetProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessMediaAsset implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $assetId, public readonly string $token)
    {
        $this->onConnection('redis')->onQueue('default');
    }

    public function backoff(): array
    {
        return [15, 60];
    }

    public function handle(MediaAssetProcessor $processor): void
    {
        try {
            $processor->process($this->assetId, $this->token);
        } catch (Throwable $exception) {
            $safeError = $exception instanceof MediaProcessingException ? $exception->getMessage() : 'Image processing or storage failed. Check worker logs before retrying.';
            $this->query()->where('processing_status', 'processing')->update([
                'processing_status' => 'pending', 'processing_error' => $safeError,
            ]);

            // Keep the persisted queue exception free of original paths and decoder details.
            Log::warning('Media processing failed', ['asset_id' => $this->assetId, 'exception' => $exception::class, 'safe_error' => $safeError]);
            throw new \RuntimeException($safeError);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->query()->whereIn('processing_status', ['pending', 'processing'])->update([
            'processing_status' => 'failed', 'processing_finished_at' => now(),
            'processing_error' => DB::raw("COALESCE(processing_error, 'Image processing exhausted its attempts or timed out. Check the source image and retry.')"),
        ]);
    }

    private function query(): Builder
    {
        return MediaAsset::query()->whereKey($this->assetId)->where('processing_token', $this->token);
    }
}
