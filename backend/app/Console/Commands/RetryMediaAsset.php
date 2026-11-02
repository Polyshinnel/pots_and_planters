<?php

namespace App\Console\Commands;

use App\Models\MediaAsset;
use App\Services\MediaAssetDispatcher;
use Illuminate\Console\Command;

class RetryMediaAsset extends Command
{
    protected $signature = 'media:retry {id : Explicit MediaAsset database ID}';

    protected $description = 'Enqueue one failed, unqueued or stale media asset; never scans or regenerates ready assets';

    public function handle(MediaAssetDispatcher $dispatcher): int
    {
        $asset = MediaAsset::query()->findOrFail($this->argument('id'));
        if (! $dispatcher->request($asset)) {
            $this->warn('Asset is ready or already has an active processing request.');

            return self::FAILURE;
        }
        if ($asset->fresh()->queued_at === null) {
            $this->error('Queue unavailable. Asset remains pending and can be retried.');

            return self::FAILURE;
        }
        $this->info('Processing requested for asset '.$asset->id.'.');

        return self::SUCCESS;
    }
}
