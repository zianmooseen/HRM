<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Payroll\UpdateEmployeeGovernmentProfileRequest;
use App\Http\Resources\EmployeeGovernmentProfileResource;
use App\Models\Employee;
use App\Models\EmployeeGovernmentProfile;
use App\Models\MohreEstablishment;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CompanyAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EmployeeGovernmentProfileController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(private readonly CompanyAccess $access, private readonly AuditLogger $audit)
    {
    }

    public function show(Request $request, Employee $employee): JsonResponse
    {
        $company = $this->company($request, 'mohre_establishments.view');
        $this->ensureOwned($employee, $company->id);

        return $this->success('Employee government profile retrieved.', [
            'employee_government_profile' => $employee->governmentProfile
                ? new EmployeeGovernmentProfileResource($employee->governmentProfile)
                : null,
        ]);
    }

    public function update(UpdateEmployeeGovernmentProfileRequest $request, Employee $employee): JsonResponse
    {
        $company = $this->company($request, 'mohre_establishments.update');
        $this->ensureOwned($employee, $company->id);
        $data = $request->validated();
        if (! empty($data['mohre_establishment_id'])) {
            MohreEstablishment::query()->where('company_id', $company->id)->findOrFail($data['mohre_establishment_id']);
        }

        $profile = EmployeeGovernmentProfile::query()->firstOrNew([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
        ]);
        $before = $profile->exists ? $profile->toArray() : null;
        $profile->fill([
            ...$data,
            'created_by' => $profile->created_by ?: $request->user()->id,
            'updated_by' => $request->user()->id,
        ])->save();
        $this->audit->log($request, 'employee_government_profile.updated', $profile, $before, $profile->toArray());

        return $this->success('Employee government profile saved.', [
            'employee_government_profile' => new EmployeeGovernmentProfileResource($profile),
        ]);
    }

    private function company(Request $request, string $permission)
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, $permission);

        return $this->access->ensureCompany($user);
    }

    private function ensureOwned(Employee $employee, int $companyId): void
    {
        abort_unless($employee->company_id === $companyId, 403, 'You are not authorized to perform this action.');
    }
}
