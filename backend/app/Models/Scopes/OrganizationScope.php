<?php

namespace App\Models\Scopes;

use App\Models;
use App\Services\Auth\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OrganizationScope implements Scope
{
    // All tenant data used by lists, nested resources, aggregates and route bindings.
    public const MODELS = [
        Models\TemporaryWorker::class, Models\TemporaryWorkerEntry::class,
        Models\Company::class, Models\Branch::class, Models\Department::class,
        Models\Employee::class, Models\JobTitle::class, Models\AttendanceRecord::class,
        Models\AttendanceCorrectionRequest::class, Models\Document::class,
        Models\LeaveRequest::class, Models\LeaveRequestStatusEvent::class,
        Models\EmployeeLeaveBalance::class, Models\LeavePayCalculationItem::class,
        Models\LeaveType::class, Models\EmployeeOnboardingCase::class,
        Models\EmployeeOnboardingTask::class, Models\OnboardingTemplate::class,
        Models\OnboardingTemplateTask::class, Models\EmployeeSalaryComponent::class,
        Models\EmployeeServicePeriod::class, Models\EmployeeTermination::class,
        Models\EmployeeGovernmentProfile::class, Models\SalaryComponent::class,
        Models\PayrollPeriod::class, Models\Payslip::class, Models\PayslipItem::class,
        Models\WpsPayrollBatch::class, Models\WpsPayrollBatchItem::class,
        Models\WpsTransferProof::class, Models\WpsComplianceAlert::class,
        Models\MohreEstablishment::class, Models\CompanyWpsSetting::class,
        Models\CompanyComplianceSetting::class, Models\EmiratisationSnapshot::class,
        Models\PublicHoliday::class, Models\CompanySubscription::class,
        Models\BillingInvoice::class, Models\AuditLog::class,
    ];

    public function apply(Builder $builder, Model $model): void
    {
        $context = OrganizationContext::current();
        if (! $context) {
            return;
        }

        if ($model instanceof Models\Company) {
            $builder->where($model->qualifyColumn('id'), $context->companyId);

            return;
        }

        if (in_array('company_id', $model->getFillable(), true)) {
            $column = $model->qualifyColumn('company_id');
            $builder->where(function (Builder $query) use ($column, $context, $model): void {
                $query->where($column, $context->companyId);
                if ($model instanceof Models\LeaveType) {
                    $query->orWhereNull($column);
                }
            });
        }

        // Children without a company column inherit their parent's tenant scope.
        if ($model instanceof Models\PayslipItem) {
            $builder->whereHas('payslip');
        } elseif ($model instanceof Models\OnboardingTemplateTask) {
            $builder->whereHas('template');
        } elseif ($model instanceof Models\WpsPayrollBatchItem) {
            $builder->whereHas('employee');
        }

        if (! $context->branchId) {
            return;
        }

        if ($model instanceof Models\Branch) {
            $builder->where($model->qualifyColumn('id'), $context->branchId);
        } elseif ($model instanceof Models\Employee || $model instanceof Models\TemporaryWorker) {
            $builder->where($model->qualifyColumn('branch_id'), $context->branchId);
        } elseif ($model instanceof Models\TemporaryWorkerEntry) {
            $builder->whereHas('temporaryWorker');
        } elseif ($model instanceof Models\Department || $model instanceof Models\MohreEstablishment) {
            // Unassigned organization structures are shared by all branches.
            $column = $model->qualifyColumn('branch_id');
            $builder->where(fn (Builder $query) => $query->where($column, $context->branchId)->orWhereNull($column));
        } elseif (in_array('employee_id', $model->getFillable(), true)) {
            $builder->whereHas('employee');
        } elseif ($model instanceof Models\PayrollPeriod) {
            $builder->whereHas('payslips');
        } elseif ($model instanceof Models\WpsPayrollBatch) {
            $builder->whereHas('items');
        } elseif ($model instanceof Models\WpsTransferProof) {
            $builder->whereHas('batch');
        } elseif ($model instanceof Models\CompanyWpsSetting) {
            $builder->whereHas('establishment');
        } elseif ($model instanceof Models\LeaveRequestStatusEvent) {
            $builder->whereHas('leaveRequest');
        } elseif ($model instanceof Models\WpsComplianceAlert) {
            $builder->whereIn('payroll_period_id', Models\PayrollPeriod::query()->select('id'));
        } elseif ($model instanceof Models\AuditLog) {
            $this->scopeAudit($builder, $context->branchId);
        }
    }

    private function scopeAudit(Builder $builder, int $branchId): void
    {
        $employeeIds = Models\Employee::withTrashed()->select('id');
        $jsonEmployeeIds = clone $employeeIds;
        if ($builder->getModel()->getConnection()->getDriverName() === 'pgsql') {
            // PostgreSQL extracts JSON IDs as text. Cast trusted IDs, not potentially malformed snapshots.
            $jsonEmployeeIds->select($builder->getModel()->getConnection()->raw('CAST(employees.id AS TEXT)'));
        }

        $builder->where(function (Builder $query) use ($branchId, $employeeIds, $jsonEmployeeIds): void {
            $query->where('before_json->branch_id', $branchId)
                ->orWhere('after_json->branch_id', $branchId)
                ->orWhereIn('before_json->employee_id', $jsonEmployeeIds)
                ->orWhereIn('after_json->employee_id', $jsonEmployeeIds)
                ->orWhere(fn (Builder $employee) => $employee->where('auditable_type', Models\Employee::class)->whereIn('auditable_id', $employeeIds))
                ->orWhere(fn (Builder $branch) => $branch->where('auditable_type', Models\Branch::class)->where('auditable_id', $branchId));
        });
    }
}
