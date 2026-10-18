<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeHorizon
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->guest(route('filament.admin.auth.login'));
        }

        abort_unless($request->user()->is_admin, 403);

        return $next($request);
    }
}
