<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaticAdminAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()
                ->route('login')
                ->with('status', 'Login first to access the admin panel.');
        }

        abort_unless($request->user()->is_active && $request->user()->role === 'admin', 403);

        return $next($request);
    }
}
