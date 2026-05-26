<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Employee\ExtendEmployeeContractRequest;
use App\Http\Requests\Employee\RehireEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\EmployeeServicePeriodResource;
use App\Models\Employee;
use App\Models\EmployeeServicePeriod;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CompanyAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeServicePeriodController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(private readonly CompanyAccess $access, private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request, Employee $employee): JsonResponse
    {
        $company = $this->company($request, 'employees.view');
        $this->ensureOwned($employee, $company->id);

        return $this->success('Employee service periods retrieved.', [
            'service_periods' => EmployeeServicePeriodResource::collection(
                $employee->servicePeriods()->orderByDesc('start_date')->orderByDesc('id')->get()
            ),
        ]);
    }

    public function extend(ExtendEmployeeContractRequest $request, Employee $employee): JsonResponse
    {
        $company = $this->company($request, 'employees.update');
        $this->ensureOwned($employee, $company->id);
        $data = $request->validated();

        $period = $employee->servicePeriods()->where('status', 'active')->latest('start_date')->first();

        if (! $period) {
            throw ValidationException::withMessages(['employee_id' => ['Employee has no active service period to extend.']]);
        }

        if ($period->end_date && $period->end_date->gte($data['end_date'])) {
            throw ValidationException::withMessages(['end_date' => ['New contract end date must be after the current end date.']]);
        }

        DB::transaction(function () use ($request, $employee, $period, $data): void {
            // Feature flow step 1: contract extensions update the active service period and employee summary date.
            $before = $period->toArray();
            $period->update([
                'end_date' => $data['end_date'],
                'change_reason' => $data['change_reason'] ?? null,
            ]);
            $employeeBefore = $employee->toArray();
            $employee->update([
                'contract_end_date' => $data['end_date'],
                'updated_by' => $request->user()->id,
            ]);
            $this->audit->log($request, 'employee_contract.extended', $period, $before, $period->fresh()->toArray());
            $this->audit->log($request, 'employee.updated', $employee, $employeeBefore, $employee->fresh()->toArray());
        });

        return $this->success('Employee contract extended.', [
            'service_period' => new EmployeeServicePeriodResource($period->fresh()),
            'employee' => new EmployeeResource($employee->fresh()->load(['branch', 'department', 'jobTitle'])),
        ]);
    }

    public function rehire(RehireEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $company = $this->company($request, 'employees.update');
        $this->ensureOwned($employee, $company->id);
        $data = $request->validated();

        if ($employee->servicePeriods()->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['employee_id' => ['Employee already has an active service period.']]);
        }

        $period = DB::transaction(function () use ($request, $company, $employee, $data): EmployeeServicePeriod {
            // Feature flow step 2: rehire creates a new service period so old settlement history stays intact.
            $period = EmployeeServicePeriod::query()->create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'employment_type' => $data['employment_type'] ?? $employee->employment_type,
                'contract_type' => $data['contract_type'] ?? $employee->contract_type,
                'status' => 'active',
                'change_reason' => $data['change_reason'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $employeeBefore = $employee->toArray();
            $employee->update([
                'status' => 'active',
                'hire_date' => $data['start_date'],
                'contract_start_date' => $data['start_date'],
                'contract_end_date' => $data['end_date'] ?? null,
                'employment_type' => $data['employment_type'] ?? $employee->employment_type,
                'contract_type' => $data['contract_type'] ?? $employee->contract_type,
                'basic_salary' => $data['basic_salary'] ?? $employee->basic_salary,
                'updated_by' => $request->user()->id,
            ]);

            $this->audit->log($request, 'employee.rehired', $period, null, $period->toArray());
            $this->audit->log($request, 'employee.updated', $employee, $employeeBefore, $employee->fresh()->toArray());

            return $period;
        });

        return $this->success('Employee rehired.', [
            'service_period' => new EmployeeServicePeriodResource($period),
            'employee' => new EmployeeResource($employee->fresh()->load(['branch', 'department', 'jobTitle'])),
        ], 201);
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
