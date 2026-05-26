<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Employee\MarkEmployeeTerminationPaidRequest;
use App\Http\Requests\Employee\StoreEmployeeTerminationRequest;
use App\Http\Resources\EmployeeTerminationResource;
use App\Models\Employee;
use App\Models\EmployeeTermination;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CompanyAccess;
use App\Services\Compliance\GratuityCalculator;
use App\Services\Compliance\LegalRuleRepository;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeTerminationController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(
        private readonly CompanyAccess $access,
        private readonly LegalRuleRepository $rules,
        private readonly GratuityCalculator $gratuity,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $this->company($request, 'employees.view');

        $terminations = $company->employeeTerminations()
            ->with('employee')
            ->when($request->query('employee_id'), fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->orderByDesc('termination_date')
            ->orderByDesc('id')
            ->get();

        return $this->success('Employee terminations retrieved.', [
            'employee_terminations' => EmployeeTerminationResource::collection($terminations),
        ]);
    }

    public function store(StoreEmployeeTerminationRequest $request, Employee $employee): JsonResponse
    {
        $company = $this->company($request, 'employees.update');
        $this->ensureEmployeeOwned($employee, $company->id);
        $data = $request->validated();

        $serviceStartDate = $this->serviceStartDate($employee);

        if (! $serviceStartDate) {
            throw ValidationException::withMessages(['employee_id' => ['Employee hire date is required before termination.']]);
        }

        $basicSalary = (float) ($data['basic_salary'] ?? $employee->basic_salary ?? 0);

        if ($basicSalary <= 0) {
            throw ValidationException::withMessages(['basic_salary' => ['A positive basic salary is required for final settlement.']]);
        }

        $termination = DB::transaction(function () use ($request, $company, $employee, $data, $basicSalary): EmployeeTermination {
            $gratuity = $this->calculateGratuity($company, $employee, $data, $basicSalary);
            $finalSettlementAmount = (float) $gratuity['gratuity_amount']
                + (float) ($data['leave_encashment_amount'] ?? 0)
                + (float) ($data['notice_paid_amount'] ?? 0)
                + (float) ($data['other_earnings_amount'] ?? 0)
                - (float) ($data['deductions_amount'] ?? 0);

            // Feature flow step 1: termination stores the final settlement snapshot before employee status is closed.
            $termination = EmployeeTermination::query()->create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'termination_date' => $data['termination_date'],
                'last_working_date' => $data['last_working_date'] ?? $data['termination_date'],
                'termination_type' => $data['termination_type'],
                'termination_reason' => $data['termination_reason'] ?? null,
                'basic_salary' => $basicSalary,
                'unpaid_leave_days' => $data['unpaid_leave_days'] ?? 0,
                'gratuity_amount' => $gratuity['gratuity_amount'],
                'leave_encashment_amount' => $data['leave_encashment_amount'] ?? 0,
                'notice_paid_amount' => $data['notice_paid_amount'] ?? 0,
                'other_earnings_amount' => $data['other_earnings_amount'] ?? 0,
                'deductions_amount' => $data['deductions_amount'] ?? 0,
                'final_settlement_amount' => max(0, $finalSettlementAmount),
                'status' => 'draft',
                'calculation_snapshot_json' => $gratuity,
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $employeeBefore = $employee->toArray();
            $employee->update([
                'status' => 'terminated',
                'contract_end_date' => $data['termination_date'],
            ]);

            $activePeriod = $employee->servicePeriods()->where('status', 'active')->latest('start_date')->first();
            if ($activePeriod) {
                $periodBefore = $activePeriod->toArray();
                $activePeriod->update([
                    'end_date' => $data['termination_date'],
                    'status' => 'terminated',
                    'change_reason' => $data['termination_reason'] ?? 'Terminated',
                    'ended_by' => $request->user()->id,
                ]);
                $this->audit->log($request, 'employee_contract.terminated', $activePeriod, $periodBefore, $activePeriod->fresh()->toArray());
            }

            $this->audit->log($request, 'employee.terminated', $employee, $employeeBefore, $employee->fresh()->toArray());
            $this->audit->log($request, 'employee_termination.created', $termination, null, $termination->toArray());

            return $termination;
        });

        return $this->success('Employee termination created.', [
            'employee_termination' => new EmployeeTerminationResource($termination->load('employee')),
        ], 201);
    }

    public function markPaid(MarkEmployeeTerminationPaidRequest $request, EmployeeTermination $termination): JsonResponse
    {
        $company = $this->company($request, 'payroll.approve');
        $this->ensureTerminationOwned($termination, $company->id);
        $data = $request->validated();
        $before = $termination->toArray();

        // Feature flow step 2: payroll records the paid final settlement separately from the HR termination event.
        $termination->update([
            'paid_amount' => $data['paid_amount'],
            'paid_at' => $data['paid_at'] ?? now(),
            'payment_reference' => $data['payment_reference'] ?? null,
            'status' => ((float) $data['paid_amount']) >= (float) $termination->final_settlement_amount ? 'paid' : 'partially_paid',
            'paid_by' => $request->user()->id,
        ]);

        $this->audit->log($request, 'employee_termination.paid', $termination, $before, $termination->fresh()->toArray());

        return $this->success('Employee termination payment recorded.', [
            'employee_termination' => new EmployeeTerminationResource($termination->fresh()->load('employee')),
        ]);
    }

    private function calculateGratuity($company, Employee $employee, array $data, float $basicSalary): array
    {
        $ruleValues = $this->rules->values();
        $serviceStartDate = $this->serviceStartDate($employee);

        return $this->gratuity->calculate([
            'start_date' => $serviceStartDate?->toDateString() ?? $employee->hire_date->toDateString(),
            'end_date' => $data['termination_date'],
            'basic_salary' => $basicSalary,
            'unpaid_leave_days' => $data['unpaid_leave_days'] ?? 0,
        ], [
            'minimum_service_years' => $ruleValues['gratuity.minimum_service_years'] ?? 1,
            'first_tier_years' => $ruleValues['gratuity.first_tier_years'] ?? 5,
            'first_tier_days_per_year' => $ruleValues['gratuity.first_tier_days_per_year'] ?? 21,
            'second_tier_days_per_year' => $ruleValues['gratuity.second_tier_days_per_year'] ?? 30,
            'daily_wage_divisor' => $ruleValues['gratuity.daily_wage_divisor'] ?? 30,
            'maximum_total_months' => $ruleValues['gratuity.maximum_total_months'] ?? 24,
            'currency' => $company->default_currency ?: 'AED',
        ]);
    }

    private function serviceStartDate(Employee $employee): ?CarbonImmutable
    {
        $period = $employee->servicePeriods()
            ->where('status', 'active')
            ->latest('start_date')
            ->first();

        return $period?->start_date
            ? CarbonImmutable::parse($period->start_date)
            : ($employee->hire_date ? CarbonImmutable::parse($employee->hire_date) : null);
    }

    private function company(Request $request, string $permission)
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, $permission);

        return $this->access->ensureCompany($user);
    }

    private function ensureEmployeeOwned(Employee $employee, int $companyId): void
    {
        abort_unless($employee->company_id === $companyId, 403, 'You are not authorized to perform this action.');
    }

    private function ensureTerminationOwned(EmployeeTermination $termination, int $companyId): void
    {
        abort_unless($termination->company_id === $companyId, 403, 'You are not authorized to perform this action.');
    }
}
