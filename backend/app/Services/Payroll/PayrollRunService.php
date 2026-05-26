<?php

namespace App\Services\Payroll;

use App\Models\PayrollPeriod;
use Illuminate\Support\Facades\DB;

class PayrollRunService
{
    public function __construct(private readonly PayrollCalculator $calculator)
    {
    }

    public function run(PayrollPeriod $period): int
    {
        return DB::transaction(function () use ($period): int {
            $created = 0;

            $period->payslips()->delete();

            $period->company->employees()
                ->where(function ($query) use ($period): void {
                    // Feature flow step 1: payroll includes active employees plus terminated employees with unpaid settlements in this period.
                    $query
                        ->where('status', 'active')
                        ->orWhereHas('terminations', function ($terminationQuery) use ($period): void {
                            $terminationQuery
                                ->whereIn('status', ['draft', 'partially_paid'])
                                ->whereBetween('termination_date', [$period->period_start, $period->period_end]);
                        });
                })
                ->orderBy('id')
                ->get()
                ->each(function ($employee) use ($period, &$created): void {
                    $calculation = $this->calculator->calculateEmployee($employee, $period);

                    // Feature flow step 2: payroll run regenerates draft payslips from current active salary data.
                    $payslip = $period->payslips()->create([
                        'company_id' => $period->company_id,
                        'employee_id' => $employee->id,
                        'gross_pay' => $calculation['gross_pay'],
                        'total_deductions' => $calculation['total_deductions'],
                        'net_pay' => $calculation['net_pay'],
                        'status' => 'draft',
                        'calculation_snapshot_json' => $calculation['snapshot'],
                    ]);

                    foreach ($calculation['items'] as $item) {
                        $payslip->items()->create($item);
                    }

                    $created++;
                });

            $period->update(['status' => 'processed']);

            return $created;
        });
    }
}
