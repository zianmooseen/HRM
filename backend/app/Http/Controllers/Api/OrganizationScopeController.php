<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Models\Branch;
use App\Models\Company;
use App\Models\PlatformClient;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OrganizationScopeController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __invoke(Request $request)
    {
        $user = $request->user()->loadMissing('roles');
        abort_unless($user->hasRole('super_admin') || $user->hasRole('company_admin'), 403);
        if (! app(TenantDatabaseManager::class)->current() && $user->hasRole('super_admin')) {
            $databases = app(TenantDatabaseManager::class);
            $clients = PlatformClient::query()->get()->keyBy('company_id');
            $companies = Company::query()->orderBy('name')->get()->flatMap(function (Company $company) use ($clients, $databases) {
                $client = $clients->get($company->id);
                if ((! $client && ! \App\Services\Tenancy\SchemaLayout::legacyTestSchema()) || ($client && ($client->provisioning_status !== 'ready' || $client->maintenance_at))) {
                    return [];
                }
                $read = fn () => Branch::query()->where('company_id', $company->id)
                    ->orderBy('name')->get(['id', 'company_id', 'name', 'code'])->toArray();

                return [[
                    'id' => $company->id, 'name' => $company->name,
                    'branches' => $client ? $databases->run($client, $read) : $read(),
                ]];
            })->values();

            return $this->success('Organization filter options retrieved.', ['companies' => $companies]);
        }
        $companyIds = $user->roles->filter(fn ($role) => $role->slug === 'company_admin' && $role->pivot->scope === 'company')->pluck('pivot.company_id');

        $companies = Company::query()
            ->when(! $user->hasRole('super_admin'), fn ($query) => $query->whereIn('id', $companyIds))
            ->with(['branches' => fn ($query) => $query->orderBy('name')->select(['id', 'company_id', 'name', 'code'])])
            ->orderBy('name')->get(['id', 'name']);

        return $this->success('Organization filter options retrieved.', ['companies' => $companies]);
    }
}
