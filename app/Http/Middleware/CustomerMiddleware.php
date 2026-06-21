<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        if ($request->user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if (! $request->user()->isCustomer()) {
            abort(403);
        }

        return $next($request);
    }
}
