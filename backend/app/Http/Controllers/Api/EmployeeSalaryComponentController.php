<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Payroll\StoreEmployeeSalaryComponentRequest;
use App\Http\Resources\EmployeeSalaryComponentResource;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\SalaryComponent;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CompanyAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EmployeeSalaryComponentController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(private readonly CompanyAccess $access, private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $this->company($request, 'payroll.view');

        $assignments = $company->employeeSalaryComponents()
            ->with(['employee', 'salaryComponent'])
            ->when($request->query('employee_id'), fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->orderByDesc('effective_from')
            ->get();

        return $this->success('Employee salary components retrieved.', [
            'employee_salary_components' => EmployeeSalaryComponentResource::collection($assignments),
        ]);
    }

    public function store(StoreEmployeeSalaryComponentRequest $request): JsonResponse
    {
        $company = $this->company($request, 'payroll.run');
        $data = $request->validated();
        $this->ensureEmployeeOwned((int) $data['employee_id'], $company->id);
        $this->ensureComponentOwned((int) $data['salary_component_id'], $company->id);

        // Feature flow step 1: salary assignments are explicit rows so payroll can replay historical settings.
        $assignment = $company->employeeSalaryComponents()->create([
            ...$data,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        $this->audit->log($request, 'employee_salary_component.created', $assignment, null, $assignment->toArray());

        return $this->success('Employee salary component assigned.', [
            'employee_salary_component' => new EmployeeSalaryComponentResource($assignment->load(['employee', 'salaryComponent'])),
        ], 201);
    }

    public function update(StoreEmployeeSalaryComponentRequest $request, EmployeeSalaryComponent $employeeSalaryComponent): JsonResponse
    {
        $company = $this->company($request, 'payroll.run');
        $this->ensureOwned($employeeSalaryComponent, $company->id);
        $data = $request->validated();
        $this->ensureEmployeeOwned((int) $data['employee_id'], $company->id);
        $this->ensureComponentOwned((int) $data['salary_component_id'], $company->id);

        $before = $employeeSalaryComponent->toArray();
        $employeeSalaryComponent->update([...$data, 'updated_by' => $request->user()->id]);
        $this->audit->log($request, 'employee_salary_component.updated', $employeeSalaryComponent, $before, $employeeSalaryComponent->fresh()->toArray());

        return $this->success('Employee salary component updated.', [
            'employee_salary_component' => new EmployeeSalaryComponentResource($employeeSalaryComponent->fresh()->load(['employee', 'salaryComponent'])),
        ]);
    }

    private function company(Request $request, string $permission)
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, $permission);

        return $this->access->ensureCompany($user);
    }

    private function ensureOwned(EmployeeSalaryComponent $assignment, int $companyId): void
    {
        abort_unless($assignment->company_id === $companyId, 403, 'You are not authorized to perform this action.');
    }

    private function ensureEmployeeOwned(int $employeeId, int $companyId): void
    {
        abort_unless(Employee::query()->whereKey($employeeId)->where('company_id', $companyId)->exists(), 422, 'Selected employee is invalid.');
    }

    private function ensureComponentOwned(int $componentId, int $companyId): void
    {
        abort_unless(SalaryComponent::query()->whereKey($componentId)->where('company_id', $companyId)->exists(), 422, 'Selected salary component is invalid.');
    }
}
