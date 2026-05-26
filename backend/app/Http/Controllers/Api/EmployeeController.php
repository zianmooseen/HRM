<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobTitle;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CompanyAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class EmployeeController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(private readonly CompanyAccess $access, private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $this->company($request, 'employees.view');

        $employees = $company->employees()
            ->with(['branch', 'department', 'jobTitle'])
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('search'), function ($query, $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('display_name', 'like', "%{$search}%")
                        ->orWhere('work_email', 'like', "%{$search}%");
                });
            })
            ->orderBy('display_name')
            ->get();

        return $this->success('Employees retrieved.', [
            'employees' => EmployeeResource::collection($employees),
        ]);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $company = $this->company($request, 'employees.create');
        $data = $this->validated($request, $company->id);

        $this->ensureUniqueEmployeeCode($company->id, $data['employee_code']);

        $employee = $company->employees()->create([
            ...$data,
            'display_name' => ($data['display_name'] ?? null) ?: trim($data['first_name'].' '.$data['last_name']),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        if ($data['hire_date'] ?? null) {
            // Feature flow step 1: employee hire creates the first auditable service period.
            $employee->servicePeriods()->create([
                'company_id' => $company->id,
                'start_date' => $data['contract_start_date'] ?? $data['hire_date'],
                'end_date' => $data['contract_end_date'] ?? null,
                'employment_type' => $data['employment_type'] ?? null,
                'contract_type' => $data['contract_type'] ?? null,
                'status' => in_array($employee->status, ['terminated', 'archived'], true) ? 'ended' : 'active',
                'change_reason' => 'Initial hire',
                'created_by' => $request->user()->id,
            ]);
        }

        $this->audit->log($request, 'employee.created', $employee, null, $employee->toArray());

        return $this->success('Employee created.', [
            'employee' => new EmployeeResource($employee->load(['branch', 'department', 'jobTitle'])),
        ], 201);
    }

    public function show(Request $request, Employee $employee): JsonResponse
    {
        $company = $this->company($request, 'employees.view');
        $this->ensureOwned($employee, $company->id);

        return $this->success('Employee retrieved.', [
            'employee' => new EmployeeResource($employee->load(['branch', 'department', 'jobTitle'])),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $company = $this->company($request, 'employees.update');
        $this->ensureOwned($employee, $company->id);

        $data = $this->validated($request, $company->id);
        $this->ensureUniqueEmployeeCode($company->id, $data['employee_code'], $employee->id);

        $before = $employee->toArray();
        $employee->update([
            ...$data,
            'display_name' => ($data['display_name'] ?? null) ?: trim($data['first_name'].' '.$data['last_name']),
            'updated_by' => $request->user()->id,
        ]);

        $this->audit->log($request, 'employee.updated', $employee, $before, $employee->fresh()->toArray());

        return $this->success('Employee updated.', [
            'employee' => new EmployeeResource($employee->fresh()->load(['branch', 'department', 'jobTitle'])),
        ]);
    }

    public function destroy(Request $request, Employee $employee): JsonResponse
    {
        $company = $this->company($request, 'employees.delete');
        $this->ensureOwned($employee, $company->id);

        $before = $employee->toArray();
        $employee->delete();
        $this->audit->log($request, 'employee.deleted', $employee, $before, null);

        return $this->success('Employee deleted.');
    }

    private function company(Request $request, string $permission)
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, $permission);

        return $this->access->ensureCompany($user);
    }

    private function validated(StoreEmployeeRequest $request, int $companyId): array
    {
        $data = $request->validated();

        foreach ([
            'branch_id' => Branch::class,
            'department_id' => Department::class,
            'job_title_id' => JobTitle::class,
        ] as $field => $model) {
            if ($data[$field] ?? null) {
                abort_unless($model::query()->whereKey($data[$field])->where('company_id', $companyId)->exists(), 422, "Selected {$field} is invalid.");
            }
        }

        return $data;
    }

    private function ensureOwned(Employee $employee, int $companyId): void
    {
        abort_unless($employee->company_id === $companyId, 403, 'You are not authorized to perform this action.');
    }

    private function ensureUniqueEmployeeCode(int $companyId, string $employeeCode, ?int $ignoreId = null): void
    {
        $exists = Employee::query()
            ->where('company_id', $companyId)
            ->where('employee_code', $employeeCode)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['employee_code' => ['The employee code is already used for this company.']]);
        }
    }
}
