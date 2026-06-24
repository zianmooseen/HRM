<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Payroll\StoreMohreEstablishmentRequest;
use App\Http\Resources\MohreEstablishmentResource;
use App\Models\MohreEstablishment;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CompanyAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MohreEstablishmentController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(private readonly CompanyAccess $access, private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $this->company($request, 'mohre_establishments.view');

        return $this->success('MoHRE establishments retrieved.', [
            'mohre_establishments' => MohreEstablishmentResource::collection(
                $company->mohreEstablishments()->with(['branch', 'wpsSetting.provider'])->orderBy('establishment_name')->get()
            ),
        ]);
    }

    public function store(StoreMohreEstablishmentRequest $request): JsonResponse
    {
        $company = $this->company($request, 'mohre_establishments.create');
        $establishment = $company->mohreEstablishments()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        $this->audit->log($request, 'mohre_establishment.created', $establishment, null, $establishment->toArray());

        return $this->success('MoHRE establishment created.', [
            'mohre_establishment' => new MohreEstablishmentResource($establishment->load('branch')),
        ], 201);
    }

    public function update(StoreMohreEstablishmentRequest $request, MohreEstablishment $establishment): JsonResponse
    {
        $company = $this->company($request, 'mohre_establishments.update');
        $this->ensureOwned($establishment, $company->id);
        $before = $establishment->toArray();
        $establishment->update([...$request->validated(), 'updated_by' => $request->user()->id]);
        $this->audit->log($request, 'mohre_establishment.updated', $establishment, $before, $establishment->fresh()->toArray());

        return $this->success('MoHRE establishment updated.', [
            'mohre_establishment' => new MohreEstablishmentResource($establishment->fresh('branch')),
        ]);
    }

    public function destroy(Request $request, MohreEstablishment $establishment): JsonResponse
    {
        $company = $this->company($request, 'mohre_establishments.update');
        $this->ensureOwned($establishment, $company->id);
        abort_if($establishment->wpsSetting()->exists(), 422, 'Remove the WPS setting before deleting this establishment.');
        $before = $establishment->toArray();
        $establishment->delete();
        $this->audit->log($request, 'mohre_establishment.deleted', $establishment, $before, null);

        return $this->success('MoHRE establishment deleted.');
    }

    private function company(Request $request, string $permission)
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, $permission);

        return $this->access->ensureCompany($user);
    }

    private function ensureOwned(MohreEstablishment $establishment, int $companyId): void
    {
        abort_unless($establishment->company_id === $companyId, 403, 'You are not authorized to perform this action.');
    }
}
