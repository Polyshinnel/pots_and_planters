<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/** Explicit diagnostic only; no business data or scheduled dispatch. */
class InfrastructureProbe implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $marker) {}

    public function handle(): void
    {
        Cache::put($this->marker, 'processed', 60);
    }
}
