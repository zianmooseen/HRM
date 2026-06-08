<?php

namespace App\Services\Payroll;

use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\WpsPayrollBatch;
use App\Models\WpsComplianceAlert;
use App\Rules\UaeIban;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class WpsReadinessService
{
    public function companyMissingFields(Company $company): array
    {
        return collect([
            'mohre_establishment_number' => $company->mohre_establishment_number,
            'wps_bank_name' => $company->wps_bank_name,
            'wps_agent_code' => $company->wps_agent_code,
            'wps_file_sender_id' => $company->wps_file_sender_id,
        ])->filter(fn ($value) => blank($value))->keys()->all();
    }

    public function employeeMissingFields(Employee $employee): array
    {
        $missing = collect([
            'work_permit_number' => $employee->work_permit_number ?: $employee->labor_card_number,
            'bank_iban' => $employee->bank_iban,
            'bank_routing_code' => $employee->bank_routing_code,
            'wps_person_id' => $employee->wps_person_id,
        ])->filter(fn ($value) => blank($value))->keys();

        if (filled($employee->bank_iban) && ! UaeIban::isValid($employee->bank_iban)) {
            $missing->push('bank_iban_invalid');
        }

        return $missing->values()->all();
    }

    public function employeeReady(Employee $employee): bool
    {
        return $this->employeeMissingFields($employee) === [];
    }

    public function summary(Company $company, ?CarbonImmutable $today = null, ?int $branchId = null): array
    {
        $today ??= CarbonImmutable::today();
        $employees = $company->employees()
            ->whereIn('status', ['active', 'on_leave'])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('display_name')
            ->get();
        $missingEmployees = $employees
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'display_name' => $employee->display_name,
                'missing_fields' => $this->employeeMissingFields($employee),
            ])
            ->filter(fn (array $employee) => $employee['missing_fields'] !== [])
            ->values();
        $latestPeriod = $company->payrollPeriods()->latest('period_start')->latest('id')->first();
        $latestBatch = $company->wpsPayrollBatches()->latest('generated_at')->latest('id')->first();
        $deadline = $this->deadline($latestPeriod, $latestBatch, $today);

        return [
            'company_setup_complete' => $this->companyMissingFields($company) === [],
            'missing_company_fields' => $this->companyMissingFields($company),
            'active_employees' => $employees->count(),
            'ready_employees' => $employees->count() - $missingEmployees->count(),
            'employees_missing_details' => $missingEmployees->count(),
            'missing_employees' => $missingEmployees->take(8)->all(),
            'latest_batch_status' => $latestBatch?->status,
            'latest_batch_id' => $latestBatch?->id,
            'alerts' => WpsComplianceAlert::query()
                ->where('company_id', $company->id)
                ->whereNull('resolved_at')
                ->latest('id')
                ->limit(5)
                ->get(['id', 'severity', 'message', 'due_date'])
                ->map(fn (WpsComplianceAlert $alert) => [
                    'id' => $alert->id,
                    'severity' => $alert->severity,
                    'message' => $alert->message,
                    'due_date' => $alert->due_date?->toDateString(),
                ])
                ->all(),
            ...$deadline,
        ];
    }

    public function missingForEmployees(Collection $employees): array
    {
        return $employees
            ->mapWithKeys(fn (Employee $employee) => [
                $employee->employee_code => $this->employeeMissingFields($employee),
            ])
            ->filter(fn (array $missing) => $missing !== [])
            ->all();
    }

    private function deadline(?PayrollPeriod $period, ?WpsPayrollBatch $batch, CarbonImmutable $today): array
    {
        if (! $period?->pay_date) {
            return [
                'payment_due_date' => null,
                'days_after_due' => null,
                'compliance_status' => 'not_scheduled',
            ];
        }

        $dueDate = CarbonImmutable::parse($period->pay_date);
        $daysAfterDue = $dueDate->diffInDays($today, false);
        $accepted = $batch
            && $batch->payroll_period_id === $period->id
            && $batch->status === 'accepted';

        $status = match (true) {
            $accepted => 'compliant',
            $daysAfterDue > 15 => 'overdue',
            $daysAfterDue >= 10 => 'urgent',
            $daysAfterDue >= 3 => 'warning',
            default => 'on_track',
        };

        return [
            'payment_due_date' => $dueDate->toDateString(),
            'days_after_due' => $daysAfterDue,
            'compliance_status' => $status,
        ];
    }
}
