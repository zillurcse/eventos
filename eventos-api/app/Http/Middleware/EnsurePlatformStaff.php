<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the Super-Admin control plane (architecture §2.1). Only platform
 * staff may reach `/admin/*`. These routes are NOT tenant-scoped — they read
 * across all organizations via the migrator (BYPASSRLS) connection.
 */
class EnsurePlatformStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user, 401);
        abort_unless($user->isPlatformStaff(), 403, 'Platform staff only.');

        return $next($request);
    }
}
