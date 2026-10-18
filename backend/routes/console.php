<?php

use App\Jobs\InfrastructureProbe;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schedule;

Artisan::command('infrastructure:ready', function () {
    DB::select('SELECT 1');
    Redis::connection()->ping();
    $this->info('Database and Redis are ready.');
})->purpose('Check infrastructure connections without requiring migrated tables');

Schedule::command('horizon:snapshot')->everyFiveMinutes()->onOneServer()->withoutOverlapping();
Schedule::call(fn () => Cache::put('infrastructure:scheduler-heartbeat', time(), 180))
    ->name('infrastructure-heartbeat')->everyMinute()->onOneServer()->withoutOverlapping();

Artisan::command('infrastructure:scheduler-status', function () {
    $lastRun = Cache::get('infrastructure:scheduler-heartbeat', 0);
    if (time() - $lastRun > 150) {
        $this->error('Scheduler heartbeat is stale.');

        return 1;
    }
    $this->info('Scheduler heartbeat is fresh.');

    return 0;
});

Artisan::command('infrastructure:queue-check', function () {
    $marker = 'infrastructure:queue-check:'.bin2hex(random_bytes(12));
    InfrastructureProbe::dispatch($marker)->onConnection('redis')->onQueue('default');
    $deadline = microtime(true) + 20;
    try {
        do {
            if (Cache::get($marker) === 'processed') {
                $this->info('Horizon processed the diagnostic job on redis/default.');

                return 0;
            }
            usleep(250000);
        } while (microtime(true) < $deadline);
        $this->error('No queue acknowledgement within 20 seconds. Inspect Horizon logs.');

        return 1;
    } finally {
        Cache::forget($marker);
    }
})->purpose('Verify a diagnostic job is actually consumed by the queue worker');

Artisan::command('infrastructure:database-fingerprint', function () {
    $this->line(hash('sha256', json_encode(DB::table('migrations')->orderBy('id')->get(), JSON_THROW_ON_ERROR)));
})->purpose('Fingerprint initial migration records to verify persistence across recreation');
