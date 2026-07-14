<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaticAdminAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get('is_admin_authenticated')) {
            return redirect()
                ->route('login')
                ->with('status', 'Login first to access the admin panel.');
        }

        return $next($request);
    }
}
