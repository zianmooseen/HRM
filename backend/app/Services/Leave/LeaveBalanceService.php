<?php

namespace App\Services\Leave;

use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveRequest;

class LeaveBalanceService
{
    public function upsertEntitlement(array $data): EmployeeLeaveBalance
    {
        $balance = EmployeeLeaveBalance::query()->firstOrNew([
            'company_id' => $data['company_id'],
            'employee_id' => $data['employee_id'],
            'leave_type_id' => $data['leave_type_id'],
            'leave_year' => $data['leave_year'],
        ]);

        $balance->fill([
            'opening_balance' => $data['opening_balance'],
            'accrued_days' => $data['accrued_days'],
            'carried_forward_days' => $data['carried_forward_days'],
            'adjusted_days' => $data['adjusted_days'],
            'encashed_days' => $data['encashed_days'] ?? $balance->encashed_days ?? 0,
        ]);

        $this->recalculate($balance);

        return $balance;
    }

    public function addPending(LeaveRequest $leaveRequest): EmployeeLeaveBalance
    {
        $balance = $this->balanceFor($leaveRequest);
        $balance->pending_days = (float) $balance->pending_days + (float) $leaveRequest->working_days;
        $this->recalculate($balance);

        return $balance;
    }

    public function approve(LeaveRequest $leaveRequest): EmployeeLeaveBalance
    {
        $balance = $this->balanceFor($leaveRequest);
        $balance->pending_days = max(0, (float) $balance->pending_days - (float) $leaveRequest->working_days);
        $balance->used_days = (float) $balance->used_days + (float) $leaveRequest->working_days;
        $this->recalculate($balance);

        return $balance;
    }

    public function availableDaysBeforeApproval(LeaveRequest $leaveRequest): float
    {
        $balance = $this->balanceFor($leaveRequest);
        $entitlement = $this->entitlementDays($balance);

        if ($entitlement <= 0) {
            return INF;
        }

        $pendingWithoutRequest = max(0, (float) $balance->pending_days - (float) $leaveRequest->working_days);

        return $entitlement
            - (float) $balance->used_days
            - $pendingWithoutRequest
            - (float) $balance->encashed_days;
    }

    public function hasConfiguredEntitlement(LeaveRequest $leaveRequest): bool
    {
        return $this->entitlementDays($this->balanceFor($leaveRequest)) > 0;
    }

    public function reject(LeaveRequest $leaveRequest): EmployeeLeaveBalance
    {
        $balance = $this->balanceFor($leaveRequest);
        $balance->pending_days = max(0, (float) $balance->pending_days - (float) $leaveRequest->working_days);
        $this->recalculate($balance);

        return $balance;
    }

    private function balanceFor(LeaveRequest $leaveRequest): EmployeeLeaveBalance
    {
        return EmployeeLeaveBalance::query()->firstOrCreate(
            [
                'company_id' => $leaveRequest->company_id,
                'employee_id' => $leaveRequest->employee_id,
                'leave_type_id' => $leaveRequest->leave_type_id,
                'leave_year' => (int) $leaveRequest->start_date->format('Y'),
            ],
        );
    }

    private function recalculate(EmployeeLeaveBalance $balance): void
    {
        // Feature flow step 2: keep balance math explicit so payroll/reporting can audit each bucket later.
        $balance->closing_balance =
            (float) $balance->opening_balance
            + (float) $balance->accrued_days
            + (float) $balance->carried_forward_days
            + (float) $balance->adjusted_days
            - (float) $balance->used_days
            - (float) $balance->pending_days
            - (float) $balance->encashed_days;

        $balance->save();
    }

    private function entitlementDays(EmployeeLeaveBalance $balance): float
    {
        return (float) $balance->opening_balance
            + (float) $balance->accrued_days
            + (float) $balance->carried_forward_days
            + (float) $balance->adjusted_days;
    }
}
