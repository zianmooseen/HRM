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

        if ($employee->status === 'active' && $basicSalary > 0) {
            // Feature flow step 1: active employees receive normal recurring salary in payroll.
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

        $employee->terminations()
            ->whereIn('status', ['draft', 'partially_paid'])
            ->whereBetween('termination_date', [$period->period_start, $period->period_end])
            ->orderBy('termination_date')
            ->get()
            ->each(function ($termination) use (&$items, &$grossPay, &$totalDeductions): void {
                // Feature flow step 2: final settlement buckets become auditable payslip items.
                foreach ([
                    'End-of-service gratuity' => ['field' => 'gratuity_amount', 'type' => 'earning'],
                    'Leave encashment' => ['field' => 'leave_encashment_amount', 'type' => 'earning'],
                    'Notice pay' => ['field' => 'notice_paid_amount', 'type' => 'earning'],
                    'Other final settlement earnings' => ['field' => 'other_earnings_amount', 'type' => 'earning'],
                    'Final settlement deductions' => ['field' => 'deductions_amount', 'type' => 'deduction'],
                ] as $label => $config) {
                    $amount = (float) $termination->{$config['field']};

                    if ($amount <= 0) {
                        continue;
                    }

                    $items[] = [
                        'salary_component_id' => null,
                        'label' => $label,
                        'type' => $config['type'],
                        'amount' => $amount,
                        'metadata_json' => [
                            'source' => 'employee_terminations',
                            'termination_id' => $termination->id,
                            'termination_date' => $termination->termination_date->toDateString(),
                            'field' => $config['field'],
                        ],
                    ];

                    if ($config['type'] === 'deduction') {
                        $totalDeductions += $amount;
                    } else {
                        $grossPay += $amount;
                    }
                }
            });

        // Feature flow step 3: generated payslips keep the exact component snapshot used by payroll.
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
