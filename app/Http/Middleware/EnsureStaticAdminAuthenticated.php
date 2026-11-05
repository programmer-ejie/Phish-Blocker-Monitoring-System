<?php

namespace App\Http\Middleware;

use App\Models\Device;
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

        abort_unless($request->user()->is_active && ($request->user()->role === 'admin' || ($request->user()->role === 'campus_admin' && in_array($request->user()->campus_name, Device::CAMPUSES, true))), 403);

        return $next($request);
    }
}
