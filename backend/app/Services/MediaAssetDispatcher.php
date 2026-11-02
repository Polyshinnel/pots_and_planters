<?php

namespace App\Services;

use App\Jobs\ProcessMediaAsset;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Throwable;

class MediaAssetDispatcher
{
    public function request(MediaAsset $asset): bool
    {
        return DB::transaction(function () use ($asset): bool {
            $locked = MediaAsset::query()->whereKey($asset->getKey())->lockForUpdate()->first();
            if (! $locked || ! $locked->canRetryProcessing()) {
                return false;
            }

            $token = (string) Str::uuid();
            $locked->forceFill([
                'processing_status' => 'pending', 'processing_token' => $token, 'queued_at' => now(),
                'processing_started_at' => null, 'processing_finished_at' => null, 'processing_error' => null,
            ])->save();

            DB::afterCommit(function () use ($locked, $token): void {
                try {
                    Queue::connection('redis')->push(new ProcessMediaAsset($locked->id, $token), '', 'default');
                } catch (Throwable $exception) {
                    MediaAsset::query()->whereKey($locked->id)->where('processing_token', $token)
                        ->where('processing_status', 'pending')->update([
                            'queued_at' => null, 'processing_error' => 'Queue unavailable. Retry processing to enqueue this image again.',
                        ]);
                    Log::warning('Media enqueue failed', ['asset_id' => $locked->id, 'exception' => $exception::class]);
                }
            });

            return true;
        }, 3);
    }
}
