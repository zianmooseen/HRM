<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\PayrollPeriod;

class PayrollCalculator
{
    public function calculateEmployee(Employee $employee, PayrollPeriod $period): array
    {
        $items = [];
        $grossPay = 0.0;
        $totalDeductions = 0.0;
        $basicSalary = (float) ($employee->basic_salary ?? 0);

        if ($basicSalary > 0) {
            $items[] = [
                'salary_component_id' => null,
                'label' => 'Basic Salary',
                'type' => 'earning',
                'amount' => $basicSalary,
                'metadata_json' => ['source' => 'employees.basic_salary'],
            ];
            $grossPay += $basicSalary;
        }

        $employee->salaryComponents()
            ->with('salaryComponent')
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', $period->period_end)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $period->period_start))
            ->get()
            ->each(function ($assignment) use (&$items, &$grossPay, &$totalDeductions): void {
                $component = $assignment->salaryComponent;

                if (! $component || $component->status !== 'active' || ! $component->is_recurring) {
                    return;
                }

                $type = $component->type === 'deduction' ? 'deduction' : 'earning';
                $amount = (float) $assignment->amount;

                $items[] = [
                    'salary_component_id' => $component->id,
                    'label' => $component->name,
                    'type' => $type,
                    'amount' => $amount,
                    'metadata_json' => [
                        'source' => 'employee_salary_components',
                        'assignment_id' => $assignment->id,
                        'component_code' => $component->code,
                    ],
                ];

                if ($type === 'deduction') {
                    $totalDeductions += $amount;
                } else {
                    $grossPay += $amount;
                }
            });

        // Feature flow step 1: generated payslips keep the exact component snapshot used by payroll.
        return [
            'gross_pay' => $grossPay,
            'total_deductions' => $totalDeductions,
            'net_pay' => $grossPay - $totalDeductions,
            'items' => $items,
            'snapshot' => [
                'employee_id' => $employee->id,
                'period_start' => $period->period_start->toDateString(),
                'period_end' => $period->period_end->toDateString(),
                'items' => $items,
            ],
        ];
    }
}
