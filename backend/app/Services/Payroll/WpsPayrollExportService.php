<?php

namespace App\Services\Payroll;

use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\WpsPayrollBatch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WpsPayrollExportService
{
    public function __construct(
        private readonly WpsFileExporterFactory $exporters,
        private readonly WpsReadinessService $readiness,
    ) {
    }

    public function generate(PayrollPeriod $period, int $userId): WpsPayrollBatch
    {
        $period->loadMissing(['company', 'payslips.employee.governmentProfile', 'mohreEstablishment.wpsSetting.provider', 'wpsProvider']);
        $this->validatePeriod($period);

        $payslips = $period->payslips
            ->where('status', 'approved')
            ->values();

        $this->validateCompany($period);
        $this->validatePayslips($payslips);

        return DB::transaction(function () use ($period, $payslips, $userId): WpsPayrollBatch {
            $existing = WpsPayrollBatch::query()
                ->where('company_id', $period->company_id)
                ->where('payroll_period_id', $period->id)
                ->first();

            if ($existing && in_array($existing->status, ['submitted', 'accepted'], true)) {
                throw ValidationException::withMessages([
                    'payroll_period_id' => ['Submitted or accepted WPS batches cannot be regenerated.'],
                ]);
            }

            $setting = $period->mohreEstablishment?->wpsSetting;
            $provider = $period->wpsProvider ?: $setting?->provider;
            $exporter = $this->exporters->make($period->company, $provider);
            $rows = $payslips->map(fn (Payslip $payslip) => $this->row($period, $payslip));
            $file = $exporter->generate($period, $rows);

            $batch = WpsPayrollBatch::query()->updateOrCreate(
                ['company_id' => $period->company_id, 'payroll_period_id' => $period->id],
                [
                    'batch_number' => $existing?->batch_number ?: $this->batchNumber($period),
                    'status' => 'generated',
                    'proof_status' => 'missing',
                    'file_format' => $file->format,
                    'provider' => $exporter->provider(),
                    'mohre_establishment_id' => $period->mohre_establishment_id,
                    'wps_provider_id' => $provider?->id,
                    'salary_month' => $period->period_start->format('Y-m'),
                    'payroll_due_date' => $period->payroll_due_date ?? $period->pay_date,
                    'record_count' => $payslips->count(),
                    'total_amount' => $payslips->sum(fn (Payslip $payslip) => (float) $payslip->net_pay),
                    'generated_at' => now(),
                    'submitted_at' => null,
                    'accepted_at' => null,
                    'rejected_at' => null,
                    'rejection_reason' => null,
                    'generated_by' => $userId,
                    'status_updated_by' => null,
                    'provider_reference' => null,
                    'bank_reference' => null,
                    'response_filename' => null,
                    'response_details_json' => null,
                    'validation_errors_json' => [],
                ],
            );

            $batch->items()->delete();

            foreach ($rows as $row) {
                $batch->items()->create([
                    'payslip_id' => $row['payslip_id'],
                    'employee_id' => $row['employee_id'],
                    'employee_code' => $row['employee_code'],
                    'employee_name' => $row['employee_name'],
                    'bank_iban' => $row['bank_iban'],
                    'bank_routing_code' => $row['bank_routing_code'],
                    'wps_person_id' => $row['wps_person_id'],
                    'fixed_income' => $row['fixed_income'],
                    'variable_income' => $row['variable_income'],
                    'net_pay' => $row['net_pay'],
                    'days_in_period' => $row['days_in_period'],
                    'row_payload_json' => $row,
                    'status' => 'included',
                ]);
            }

            $batch->update(['file_content' => $file->content]);
            $batch->update(['file_hash' => hash('sha256', $file->content)]);
            $period->update([
                'wps_status' => 'file_generated',
                'mohre_establishment_id' => $period->mohre_establishment_id ?: $setting?->mohre_establishment_id,
                'wps_provider_id' => $period->wps_provider_id ?: $provider?->id,
            ]);

            return $batch->fresh(['payrollPeriod', 'items']);
        });
    }

    private function validatePeriod(PayrollPeriod $period): void
    {
        if ($period->status !== 'approved') {
            throw ValidationException::withMessages([
                'payroll_period_id' => ['Only approved payroll periods can be exported for WPS.'],
            ]);
        }

        if ($period->payslips->where('status', 'approved')->isEmpty()) {
            throw ValidationException::withMessages([
                'payroll_period_id' => ['Approved payslips are required before generating a WPS batch.'],
            ]);
        }
    }

    private function validateCompany(PayrollPeriod $period): void
    {
        $missing = collect($this->readiness->companyMissingFields($period->company));

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'company' => ['Company WPS setup is incomplete: '.$missing->implode(', ').'.'],
            ]);
        }
    }

    private function validatePayslips(Collection $payslips): void
    {
        $errors = [];

        foreach ($payslips as $payslip) {
            $employee = $payslip->employee;
            $missing = collect($employee ? $this->readiness->employeeMissingFields($employee) : ['employee']);

            if ($missing->isNotEmpty()) {
                $employeeLabel = $employee
                    ? "{$employee->display_name} ({$employee->employee_code})"
                    : "Employee #{$payslip->employee_id}";
                $reasons = $missing
                    ->map(fn (string $field): string => $this->employeeReadinessMessage($field))
                    ->implode(' ');

                $errors[] = "{$employeeLabel}: {$reasons}";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['employees' => $errors]);
        }
    }

    private function employeeReadinessMessage(string $field): string
    {
        return match ($field) {
            'work_permit_number' => 'Add a work permit or labour card number.',
            'bank_iban' => 'Add the employee\'s UAE bank IBAN.',
            'bank_iban_invalid' => 'Correct the bank IBAN because it is not a valid UAE IBAN.',
            'bank_routing_code' => 'Add the bank routing code.',
            'wps_person_id' => 'Add the employee\'s MoHRE or WPS identifier.',
            'employee' => 'The employee record could not be loaded.',
            default => 'Complete the missing WPS payroll information.',
        };
    }

    private function row(PayrollPeriod $period, Payslip $payslip): array
    {
        $employee = $payslip->employee;
        $days = CarbonImmutable::parse($period->period_start)->diffInDays(CarbonImmutable::parse($period->period_end)) + 1;
        $basicSalary = min((float) ($employee->basic_salary ?? 0), (float) $payslip->net_pay);
        $variableIncome = max((float) $payslip->net_pay - $basicSalary, 0);

        return [
            'record_type' => 'EDR',
            'payslip_id' => $payslip->id,
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'employee_name' => $employee->display_name,
            'wps_person_id' => $employee->governmentProfile?->wps_employee_identifier ?: $employee->wps_person_id,
            'bank_routing_code' => $employee->bank_routing_code,
            'bank_iban' => $employee->bank_iban,
            'salary_month' => $period->period_start->format('Y-m'),
            'fixed_income' => round($basicSalary, 2),
            'variable_income' => round($variableIncome, 2),
            'net_pay' => round((float) $payslip->net_pay, 2),
            'days_in_period' => $days,
        ];
    }

    private function batchNumber(PayrollPeriod $period): string
    {
        return sprintf('WPS-%d-%s-%04d', $period->company_id, $period->period_start->format('Ym'), $period->id);
    }
}
