<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\PlatformClient;
use App\Services\Tenancy\SupportActor;
use App\Services\Tenancy\TenantDatabaseManager;
use Closure;
use Illuminate\Http\Request;

class EnterClientForSupport
{
    public function __construct(private TenantDatabaseManager $databases) {}

    public function handle(Request $request, Closure $next)
    {
        if ($this->databases->current() || $request->is('api/companies') || ! $request->user()->hasRole('super_admin')) {
            return $next($request);
        }
        // 1. Support selection is available only to an authenticated platform administrator.
        $request->validate(['scope_company_id' => ['nullable', 'integer', 'min:1']]);
        $companyId = $request->integer('scope_company_id');
        if (! $companyId) {
            abort_if(PlatformClient::query()->exists(), 422, 'Select a client company before opening HR records.');

            return $next($request);
        }
        $client = PlatformClient::query()->where('company_id', $companyId)->first();
        if (! $client) {
            return $next($request); // Existing local sample companies.
        }
        $actor = $request->user();
        $resolver = $request->getUserResolver();
        // 2. Record support access outside the tenant's rollback boundary.
        AuditLog::on(config('tenancy.central_connection'))->create([
            'company_id' => $companyId, 'actor_user_id' => $actor->id, 'action' => 'client.support_access',
            'auditable_type' => PlatformClient::class, 'auditable_id' => $client->id,
            'after_json' => ['method' => $request->method(), 'path' => $request->path()],
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);

        try {
            return $this->databases->run($client, function () use ($actor, $request, $next, $client) {
                // 3. A local non-login actor preserves FK integrity for tenant audit/write records.
                $support = app(SupportActor::class)->forAdministrator($client, $actor);
                $request->setUserResolver(fn () => $support);

                return $next($request);
            });
        } finally {
            $request->setUserResolver($resolver);
        }
    }
}
