<?php

namespace Tests\Feature;

use App\Jobs\ProcessMediaAsset;
use App\Models\MediaAsset;
use App\Services\MediaAssetDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MediaQueueWorkerTest extends TestCase
{
    public function test_real_redis_workers_process_concurrent_duplicates_in_an_isolated_test_namespace(): void
    {
        // Real workers need committed rows and real shared storage, not RefreshDatabase or Storage::fake.
        $this->assertSame('potsandplanters_test', DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $prefix = 'media-test-'.Str::uuid().':';
        config(['database.redis.options.prefix' => $prefix]);
        // RedisManager snapshots config when resolved; purge alone retains its old prefix.
        $this->app->forgetInstance('redis');
        Redis::clearResolvedInstance('redis');
        $this->app->forgetInstance('queue');
        Queue::clearResolvedInstance('queue');
        $this->assertSame($prefix, Redis::connection()->client()->getOption(\Redis::OPT_PREFIX));
        $asset = null;
        $broken = null;
        $workers = [];
        $path = 'product-originals/test-'.Str::uuid().'.png';
        $brokenPath = 'product-originals/test-'.Str::uuid().'.png';

        try {
            $bytes = (string) ImageManager::gd()->create(1200, 600)->fill('red')->toPng();
            Storage::disk('local')->put($path, $bytes);
            $asset = MediaAsset::query()->create(['disk' => 'local', 'path' => $path, 'mime_type' => 'image/png', 'size_bytes' => strlen($bytes)])->refresh();
            $this->assertTrue(app(MediaAssetDispatcher::class)->request($asset));
            Queue::connection('redis')->push(new ProcessMediaAsset($asset->id, $asset->fresh()->processing_token), '', 'default');
            $this->assertSame(2, Queue::connection('redis')->size('default'));

            for ($i = 0; $i < 2; $i++) {
                $script = 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; '
                    .'$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); '
                    .'$db = Illuminate\\Support\\Facades\\DB::selectOne("SELECT DATABASE() AS name")->name; '
                    .'if ($db !== "potsandplanters_test") { fwrite(STDERR, "Wrong worker database"); exit(2); } '
                    .'exit($app->handleCommand(new Symfony\\Component\\Console\\Input\\ArgvInput(["artisan", "queue:work", "redis", "--queue=default", "--once", "--timeout=45"])));';
                $worker = new Process([PHP_BINARY, '-r', $script], base_path(), [
                    'APP_ENV' => 'testing', 'DB_DATABASE' => 'potsandplanters_test', 'DB_URL' => '',
                    'REDIS_PREFIX' => $prefix, 'QUEUE_FAILED_DRIVER' => 'null', 'CACHE_STORE' => 'array',
                ]);
                $worker->setTimeout(60)->start();
                $workers[] = $worker;
            }
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertSame(0, $worker->getExitCode(), $worker->getOutput().$worker->getErrorOutput());
            }

            $asset->refresh();
            $this->assertSame('ready', $asset->processing_status, json_encode([
                'error' => $asset->processing_error, 'queue_size' => Queue::connection('redis')->size('default'),
                'workers' => array_map(fn ($worker) => $worker->getOutput().$worker->getErrorOutput(), $workers),
            ]));
            $this->assertCount(3, $asset->variants);
            foreach ($asset->variants as $variant) {
                $this->assertSame(0755, fileperms(Storage::disk('public')->path(dirname($variant['path']))) & 0777);
                $this->assertSame(0644, fileperms(Storage::disk('public')->path($variant['path'])) & 0777);
            }
            $this->assertCount(3, Storage::disk('public')->allFiles('product-media/'.$asset->public_id));
            $this->assertSame(0, Queue::connection('redis')->size('default'));
            $this->assertNull($asset->processing_error);

            Storage::disk('local')->put($brokenPath, 'corrupt image');
            $broken = MediaAsset::query()->create(['disk' => 'local', 'path' => $brokenPath, 'mime_type' => 'image/png', 'size_bytes' => 13])->refresh();
            $this->assertTrue(app(MediaAssetDispatcher::class)->request($broken));
            $payload = json_decode(Redis::connection()->lpop('queues:default'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(3, $payload['maxTries']);
            $this->assertSame('15,60', $payload['backoff']);
            // Keep the real three-attempt lifecycle; remove only its wall-clock delay in this test.
            $payload['backoff'] = '0';
            Queue::connection('redis')->pushRaw(json_encode($payload, JSON_THROW_ON_ERROR), 'default');
            $retryScript = str_replace('exit($app->handleCommand',
                'Illuminate\\Support\\Facades\\Event::listen(Illuminate\\Queue\\Events\\JobProcessing::class, function () { echo "media-attempt", PHP_EOL; }); exit($app->handleCommand', $script);
            $retryScript = str_replace('"--once"', '"--stop-when-empty", "--sleep=0"', $retryScript);
            $retryWorker = new Process([PHP_BINARY, '-r', $retryScript], base_path(), $worker->getEnv());
            $retryWorker->setTimeout(60)->start();
            $workers[] = $retryWorker;
            $retryWorker->wait();
            $this->assertSame(0, $retryWorker->getExitCode(), $retryWorker->getErrorOutput());
            $this->assertSame(3, substr_count($retryWorker->getOutput(), 'media-attempt'));
            $this->assertSame('failed', $broken->fresh()->processing_status);
            $this->assertNotNull($broken->fresh()->processing_error);
            $this->assertStringNotContainsString($brokenPath, $broken->fresh()->processing_error);
            $this->assertNull($broken->fresh()->variantUrl());
            $this->assertSame([], Storage::disk('public')->allFiles('product-media/'.$broken->public_id));
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            if ($asset) {
                Storage::disk('public')->deleteDirectory('product-media/'.$asset->public_id);
                Storage::disk('local')->deleteDirectory('media-processing/'.$asset->public_id);
                $asset->delete();
            }
            if ($broken) {
                Storage::disk('public')->deleteDirectory('product-media/'.$broken->public_id);
                Storage::disk('local')->deleteDirectory('media-processing/'.$broken->public_id);
                $broken->delete();
            }
            Storage::disk('local')->delete($path);
            Storage::disk('local')->delete($brokenPath);
            // Only the keys belonging to this test's random prefix; never FLUSHDB.
            Redis::connection()->del('queues:default', 'queues:default:reserved', 'queues:default:delayed', 'queues:default:notify');
            Redis::purge('default');
        }
    }
}
