<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;

class HorizonServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Explicit authorization overrides Horizon's default local-environment bypass.
        Horizon::auth(fn ($request): bool => $request->user()?->is_admin === true);
    }
}
