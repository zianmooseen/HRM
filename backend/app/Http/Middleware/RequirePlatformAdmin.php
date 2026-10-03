<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantDatabaseManager;
use Closure;
use Illuminate\Http\Request;

class RequirePlatformAdmin
{
    public function handle(Request $request, Closure $next)
    {
        abort_if(app(TenantDatabaseManager::class)->current(), 403);
        abort_unless($request->user()?->hasRole('super_admin'), 403);

        return $next($request);
    }
}
