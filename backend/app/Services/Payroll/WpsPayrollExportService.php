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
    public function generate(PayrollPeriod $period, int $userId): WpsPayrollBatch
    {
        $period->loadMissing(['company', 'payslips.employee']);
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

            $batch = WpsPayrollBatch::query()->updateOrCreate(
                ['company_id' => $period->company_id, 'payroll_period_id' => $period->id],
                [
                    'batch_number' => $existing?->batch_number ?: $this->batchNumber($period),
                    'status' => 'generated',
                    'file_format' => 'csv',
                    'salary_month' => $period->period_start->format('Y-m'),
                    'record_count' => $payslips->count(),
                    'total_amount' => $payslips->sum(fn (Payslip $payslip) => (float) $payslip->net_pay),
                    'generated_at' => now(),
                    'submitted_at' => null,
                    'accepted_at' => null,
                    'rejected_at' => null,
                    'rejection_reason' => null,
                    'generated_by' => $userId,
                    'validation_errors_json' => [],
                ],
            );

            $batch->items()->delete();

            $rows = $payslips->map(fn (Payslip $payslip) => $this->row($period, $payslip));
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

            $batch->update(['file_content' => $this->csv($period, $rows)]);

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
        $missing = collect([
            'mohre_establishment_number' => $period->company->mohre_establishment_number,
            'wps_agent_code' => $period->company->wps_agent_code,
            'wps_file_sender_id' => $period->company->wps_file_sender_id,
        ])->filter(fn ($value) => blank($value))->keys();

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
            $missing = collect([
                'bank_iban' => $employee?->bank_iban,
                'bank_routing_code' => $employee?->bank_routing_code,
                'wps_person_id' => $employee?->wps_person_id,
            ])->filter(fn ($value) => blank($value))->keys();

            if ($missing->isNotEmpty()) {
                $errors[] = ($employee?->employee_code ?? 'Employee #'.$payslip->employee_id).' missing '.$missing->implode(', ');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['employees' => $errors]);
        }
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
            'wps_person_id' => $employee->wps_person_id,
            'bank_routing_code' => $employee->bank_routing_code,
            'bank_iban' => $employee->bank_iban,
            'salary_month' => $period->period_start->format('Y-m'),
            'fixed_income' => round($basicSalary, 2),
            'variable_income' => round($variableIncome, 2),
            'net_pay' => round((float) $payslip->net_pay, 2),
            'days_in_period' => $days,
        ];
    }

    private function csv(PayrollPeriod $period, Collection $rows): string
    {
        $lines = [
            $this->csvLine(['Record Type', 'Employee Code', 'Employee Name', 'WPS Person ID', 'Routing Code', 'IBAN', 'Salary Month', 'Fixed Income', 'Variable Income', 'Net Pay', 'Days']),
        ];

        foreach ($rows as $row) {
            $lines[] = $this->csvLine([
                $row['record_type'],
                $row['employee_code'],
                $row['employee_name'],
                $row['wps_person_id'],
                $row['bank_routing_code'],
                $row['bank_iban'],
                $row['salary_month'],
                number_format($row['fixed_income'], 2, '.', ''),
                number_format($row['variable_income'], 2, '.', ''),
                number_format($row['net_pay'], 2, '.', ''),
                $row['days_in_period'],
            ]);
        }

        $lines[] = $this->csvLine([
            'SCR',
            $period->company->mohre_establishment_number,
            $period->company->wps_agent_code,
            $period->company->wps_file_sender_id,
            $period->period_start->format('Y-m'),
            $rows->count(),
            number_format($rows->sum('net_pay'), 2, '.', ''),
        ]);

        return implode("\n", $lines)."\n";
    }

    private function csvLine(array $values): string
    {
        return collect($values)
            ->map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"')
            ->implode(',');
    }

    private function batchNumber(PayrollPeriod $period): string
    {
        return sprintf('WPS-%d-%s-%04d', $period->company_id, $period->period_start->format('Ym'), $period->id);
    }
}
