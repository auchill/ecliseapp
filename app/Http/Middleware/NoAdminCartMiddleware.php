<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NoAdminCartMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isAdmin()) {
            if ($request->expectsJson()) {
                abort(403);
            }

            return redirect()->route('admin.dashboard')->with('status', 'Admin users cannot use customer cart, checkout, or repair booking flows.');
        }

        return $next($request);
    }
}
