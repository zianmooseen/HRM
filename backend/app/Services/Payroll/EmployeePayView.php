<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\Payslip;
use Illuminate\Database\Eloquent\Builder;

class EmployeePayView
{
    public function approved(Employee $employee): Builder
    {
        // 1. Resolve ownership from the signed-in employee and release only approved payroll.
        return Payslip::query()->where('payslips.employee_id', $employee->id)
            ->where('payslips.company_id', $employee->company_id)->where('payslips.status', 'approved')
            ->whereHas('payrollPeriod', fn ($query) => $query
                ->where('payroll_periods.company_id', $employee->company_id)->where('payroll_periods.status', 'approved'));
    }

    public function latest(Employee $employee): Builder
    {
        return $this->approved($employee)->with(['payrollPeriod', 'items'])
            ->join('payroll_periods', 'payroll_periods.id', '=', 'payslips.payroll_period_id')
            ->select('payslips.*')->reorder()->orderByDesc('payroll_periods.period_end')->orderByDesc('payslips.id');
    }

    public function overview(Employee $employee, int $year): array
    {
        $company = $employee->company;
        $today = now($company->timezone)->toDateString();
        // 2. YTD is approved statement totals by scheduled pay date, not bank payment confirmation.
        $totals = $this->approved($employee)->whereHas('payrollPeriod', fn ($query) => $query
            ->whereYear('pay_date', $year)->whereDate('pay_date', '<=', $today))
            ->selectRaw('COUNT(*) as statements, COALESCE(SUM(gross_pay), 0) as gross, COALESCE(SUM(total_deductions), 0) as deductions, COALESCE(SUM(net_pay), 0) as net')
            ->first();
        $iban = $employee->bank_iban;

        return [
            'currency' => $company->default_currency,
            'compensation' => ['basic_salary' => $employee->basic_salary, 'monthly_salary' => $employee->monthly_salary],
            'direct_deposit' => ['bank_name' => $employee->bank_name, 'masked_iban' => $iban ? '•••• '.substr($iban, -4) : null],
            'ytd' => [
                'year' => $year, 'through_date' => $today, 'statements' => (int) $totals->statements,
                'gross_pay' => number_format((float) $totals->gross, 2, '.', ''),
                'total_deductions' => number_format((float) $totals->deductions, 2, '.', ''),
                'net_pay' => number_format((float) $totals->net, 2, '.', ''),
            ],
        ];
    }
}
