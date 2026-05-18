<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CompanyAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CompanyController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(
        private readonly CompanyAccess $access,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, 'companies.view');

        $companies = $user->hasRole('super_admin')
            ? Company::query()->orderBy('name')->get()
            : $user->scopedCompanies()->orderBy('name')->get();

        return $this->success('Companies retrieved.', [
            'companies' => CompanyResource::collection($companies),
        ]);
    }

    public function current(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, 'companies.view');

        return $this->success('Current company retrieved.', [
            'company' => new CompanyResource($this->access->ensureCompany($user)),
        ]);
    }

    public function update(UpdateCompanyRequest $request): JsonResponse
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, 'companies.update');

        $company = $this->access->ensureCompany($user);
        $before = $company->toArray();

        $company->update($request->validated());

        $this->audit->log($request, 'company.updated', $company, $before, $company->fresh()->toArray());

        return $this->success('Company updated.', [
            'company' => new CompanyResource($company->fresh()),
        ]);
    }
}
