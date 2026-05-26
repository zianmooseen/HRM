<?php

namespace App\Services\Leave;

use App\Models\LeavePayCalculationItem;
use App\Models\LeaveRequest;
use App\Services\Compliance\LegalRuleRepository;
use Illuminate\Validation\ValidationException;

class SickLeavePayService
{
    public function __construct(
        private readonly SickLeaveCalculator $calculator,
        private readonly LegalRuleRepository $rules,
    ) {
    }

    public function calculate(LeaveRequest $leaveRequest): array
    {
        $leaveRequest->loadMissing(['employee', 'leaveType']);

        abort_unless($leaveRequest->leaveType?->code === 'sick_leave', 422, 'Only sick leave requests can use sick leave pay calculation.');

        $employee = $leaveRequest->employee;
        $basicSalary = (float) ($employee->basic_salary ?? 0);

        if ($basicSalary <= 0) {
            throw ValidationException::withMessages(['employee_id' => ['Employee basic salary is required for sick leave pay calculation.']]);
        }

        $ruleValues = $this->rules->values();
        $rules = [
            'max_days_per_year' => $ruleValues['sick_leave.max_days_per_year'] ?? 90,
            'full_pay_days' => $ruleValues['sick_leave.full_pay_days'] ?? 15,
            'half_pay_days' => $ruleValues['sick_leave.half_pay_days'] ?? 30,
            'unpaid_days' => $ruleValues['sick_leave.unpaid_days'] ?? 45,
            'medical_document_required' => $ruleValues['sick_leave.medical_report_required'] ?? true,
            'notification_days' => $ruleValues['sick_leave.notification_days'] ?? 3,
            'paid_sick_leave_during_probation' => false,
            'calculation_basis' => 'basic_salary_30_day_divisor',
        ];

        // Feature flow step 1: prior approved sick leave in the same year consumes the UAE pay tiers.
        $previouslyUsedDays = LeaveRequest::query()
            ->where('company_id', $leaveRequest->company_id)
            ->where('employee_id', $leaveRequest->employee_id)
            ->where('leave_type_id', $leaveRequest->leave_type_id)
            ->where('status', 'approved')
            ->whereYear('start_date', $leaveRequest->start_date->year)
            ->whereKeyNot($leaveRequest->id)
            ->sum('working_days');

        $result = $this->calculator->calculate([
            'requested_days' => (float) $leaveRequest->working_days,
            'previously_used_days' => (float) $previouslyUsedDays,
            'daily_wage' => $basicSalary / 30,
            'is_in_probation' => $employee->probation_end_date && $employee->probation_end_date->gte($leaveRequest->start_date),
            'has_medical_document' => (bool) $leaveRequest->medical_certificate_document_id,
        ], $rules);

        return [
            ...$result,
            'previously_used_days' => (float) $previouslyUsedDays,
        ];
    }

    public function storeForApprovedRequest(LeaveRequest $leaveRequest): array
    {
        $calculation = $this->calculate($leaveRequest);

        if (! ($calculation['eligible'] ?? false)) {
            throw ValidationException::withMessages([
                'medical_certificate_document_id' => ['Medical document is required before approving sick leave.'],
            ]);
        }

        LeavePayCalculationItem::query()
            ->where('leave_request_id', $leaveRequest->id)
            ->delete();

        foreach ($calculation['items'] as $item) {
            LeavePayCalculationItem::query()->create([
                'company_id' => $leaveRequest->company_id,
                'leave_request_id' => $leaveRequest->id,
                'employee_id' => $leaveRequest->employee_id,
                'leave_type_id' => $leaveRequest->leave_type_id,
                'pay_tier' => $item['pay_tier'],
                'days' => $item['days'],
                'pay_percentage' => $item['pay_percentage'],
                'daily_wage' => $item['daily_wage'],
                'gross_pay_amount' => $item['gross_pay_amount'],
                'deduction_amount' => $item['deduction_amount'],
                'calculation_basis' => $item['calculation_basis'],
                'rule_snapshot_json' => $calculation['rule_snapshot'],
            ]);
        }

        // Feature flow step 2: payroll can later consume stored leave pay rows instead of recalculating history.
        return $calculation;
    }
}
