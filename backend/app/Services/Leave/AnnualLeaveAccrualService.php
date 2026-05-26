<?php

namespace App\Services\Leave;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveType;
use Carbon\CarbonImmutable;

class AnnualLeaveAccrualService
{
    public function __construct(private readonly LeaveBalanceService $balances)
    {
    }

    public function accrue(Company $company, int $leaveYear, ?int $employeeId = null, ?string $accrualDate = null): array
    {
        $annualLeaveType = LeaveType::query()
            ->where('code', 'annual_leave')
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $company->id))
            ->firstOrFail();
        $asOfDate = $this->accrualDate($leaveYear, $accrualDate);

        $employees = Employee::query()
            ->where('company_id', $company->id)
            ->whereIn('status', ['active', 'onboarding', 'terminated'])
            ->whereNotNull('hire_date')
            ->when($employeeId, fn ($query) => $query->whereKey($employeeId))
            ->orderBy('id')
            ->get();

        $balances = [];

        foreach ($employees as $employee) {
            $employeeAccrualDate = $this->employeeAccrualDate($employee, $asOfDate, $leaveYear);

            if ($employeeAccrualDate === null || ! $this->serviceStartDate($employee)) {
                continue;
            }

            // Feature flow step 1: UAE statutory annual leave entitlement is generated from hire date.
            $existing = EmployeeLeaveBalance::query()
                ->where('company_id', $company->id)
                ->where('employee_id', $employee->id)
                ->where('leave_type_id', $annualLeaveType->id)
                ->where('leave_year', $leaveYear)
                ->first();

            $balances[] = $this->balances->upsertEntitlement([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'leave_type_id' => $annualLeaveType->id,
                'leave_year' => $leaveYear,
                'opening_balance' => $existing?->opening_balance ?? 0,
                'accrued_days' => $this->entitlementDays($employee, $employeeAccrualDate),
                'carried_forward_days' => $existing?->carried_forward_days ?? 0,
                'adjusted_days' => $existing?->adjusted_days ?? 0,
                'encashed_days' => $existing?->encashed_days ?? 0,
            ])->load(['employee', 'leaveType']);
        }

        return [
            'leave_year' => $leaveYear,
            'accrual_date' => $asOfDate->toDateString(),
            'balances' => $balances,
            'processed_count' => count($balances),
        ];
    }

    private function employeeAccrualDate(Employee $employee, CarbonImmutable $asOfDate, int $leaveYear): ?CarbonImmutable
    {
        if ($employee->status !== 'terminated') {
            return $asOfDate;
        }

        if (! $employee->contract_end_date) {
            return null;
        }

        if ((int) $employee->contract_end_date->format('Y') < $leaveYear) {
            return null;
        }

        if ((int) $employee->contract_end_date->format('Y') === $leaveYear && $employee->contract_end_date->lessThan($asOfDate)) {
            return CarbonImmutable::parse($employee->contract_end_date)->endOfDay();
        }

        return $asOfDate;
    }

    public function entitlementDays(Employee $employee, CarbonImmutable $asOfDate): float
    {
        $serviceStartDate = $this->serviceStartDate($employee);

        if (! $serviceStartDate || $serviceStartDate->greaterThan($asOfDate)) {
            return 0.0;
        }

        $completedMonths = (int) $serviceStartDate->diffInMonths($asOfDate);

        if ($completedMonths < 6) {
            return 0.0;
        }

        if ($completedMonths < 12) {
            return min(30.0, $completedMonths * 2.0);
        }

        return 30.0;
    }

    private function serviceStartDate(Employee $employee): ?CarbonImmutable
    {
        $period = $employee->servicePeriods()
            ->whereIn('status', ['active', 'terminated'])
            ->latest('start_date')
            ->first();

        return $period?->start_date
            ? CarbonImmutable::parse($period->start_date)
            : ($employee->hire_date ? CarbonImmutable::parse($employee->hire_date) : null);
    }

    private function accrualDate(int $leaveYear, ?string $accrualDate): CarbonImmutable
    {
        if ($accrualDate) {
            return CarbonImmutable::parse($accrualDate)->endOfDay();
        }

        $endOfYear = CarbonImmutable::create($leaveYear, 12, 31)->endOfDay();
        $today = CarbonImmutable::now()->endOfDay();

        return $leaveYear === (int) $today->format('Y') && $today->lessThan($endOfYear)
            ? $today
            : $endOfYear;
    }
}
