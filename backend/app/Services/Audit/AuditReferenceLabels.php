<?php

namespace App\Services\Audit;

use App\Models;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuditReferenceLabels
{
    // Explicit reference fields only: amounts, counts and external identifiers stay unchanged.
    private const REFERENCES = [
        'temporary_worker_id' => [Models\TemporaryWorker::class, 'name'],
        'role_id' => [Models\Role::class, 'name'],
        'branch_id' => [Models\Branch::class, 'name'],
        'department_id' => [Models\Department::class, 'name'],
        'job_title_id' => [Models\JobTitle::class, 'title'],
        'employee_id' => [Models\Employee::class, 'display_name'],
        'company_id' => [Models\Company::class, 'name'],
        'legal_rule_set_id' => [Models\LegalRuleSet::class, 'name'],
        'leave_type_id' => [Models\LeaveType::class, 'name'],
        'salary_component_id' => [Models\SalaryComponent::class, 'name'],
        'onboarding_template_id' => [Models\OnboardingTemplate::class, 'name'],
        'mohre_establishment_id' => [Models\MohreEstablishment::class, 'establishment_name'],
        'wps_provider_id' => [Models\WpsProvider::class, 'name'],
        'subscription_plan_id' => [Models\SubscriptionPlan::class, 'name'],
        'document_id' => [Models\Document::class, 'title'],
        'user_id' => [Models\User::class, 'name'],
        'payroll_period_id' => [Models\PayrollPeriod::class, 'period_start', 'period_end'],
        'wps_payroll_batch_id' => [Models\WpsPayrollBatch::class, 'batch_number'],
        'attendance_record_id' => [Models\AttendanceRecord::class, 'date'],
        'leave_request_id' => [Models\LeaveRequest::class, 'start_date', 'end_date'],
        'employee_onboarding_task_id' => [Models\EmployeeOnboardingTask::class, 'title'],
        'billing_invoice_id' => [Models\BillingInvoice::class, 'invoice_number'],
    ];

    private const ALIASES = [
        'role_ids' => 'role_id', 'roles' => 'role_id',
        'employee_ids' => 'employee_id', 'manager_employee_id' => 'employee_id',
        'branch_ids' => 'branch_id', 'department_ids' => 'department_id',
        'plan_id' => 'subscription_plan_id', 'medical_certificate_document_id' => 'document_id',
        'actor_user_id' => 'user_id', 'created_by' => 'user_id', 'updated_by' => 'user_id',
        'approved_by' => 'user_id', 'rejected_by' => 'user_id', 'requested_by' => 'user_id',
        'uploaded_by' => 'user_id', 'verified_by' => 'user_id', 'locked_by' => 'user_id',
        'deleted_by' => 'user_id', 'generated_by' => 'user_id', 'paid_by' => 'user_id',
        'status_updated_by' => 'user_id',
        'ended_by' => 'user_id', 'assigned_to_user_id' => 'user_id', 'completed_by' => 'user_id',
    ];

    public function forLog(Models\AuditLog $log, Models\User $user): array
    {
        $references = self::REFERENCES;
        foreach (self::REFERENCES as $definition) {
            if ($definition[0] === $log->auditable_type) {
                $references['_record'] = $definition;
                break;
            }
        }
        $requested = [];
        $collect = function (array $values) use (&$collect, &$requested, $references): void {
            foreach ($values as $key => $value) {
                $canonical = self::ALIASES[$key] ?? $key;
                if (isset($references[$canonical])) {
                    $ids = is_array($value) ? $value : [$value];
                    foreach ($ids as $id) {
                        if (is_array($id)) {
                            $id = $id['id'] ?? null;
                        }
                        if ((is_int($id) || is_string($id)) && filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false) {
                            $requested[$key][] = $id;
                        }
                    }
                }
                if (is_array($value)) {
                    $collect($value);
                }
            }
        };
        // 1. Read both snapshots so previous and replacement references can be described.
        $collect($log->before_json ?? []);
        $collect($log->after_json ?? []);
        $collect(['actor_user_id' => $log->actor_user_id, '_record' => $log->auditable_id]);
        // 2. Resolve whitelisted models with company, branch and module restrictions intact.
        $labels = [];
        foreach ($requested as $key => $ids) {
            $canonical = self::ALIASES[$key] ?? $key;
            [$class, $column] = $references[$canonical];
            $labels[$key] = [];
            if ($class === Models\TemporaryWorker::class && ! $user->hasPermission('temporary_workers.view')) {
                continue;
            }
            if ($class === Models\Document::class && ! $user->hasPermission('documents.view')) {
                continue;
            }
            if (in_array($class, [Models\SalaryComponent::class, Models\PayrollPeriod::class, Models\WpsPayrollBatch::class], true) && ! $user->hasPermission('payroll.view')) {
                continue;
            }
            $model = new $class;
            $query = $class::query()->whereIn($model->qualifyColumn('id'), array_unique($ids));
            if (in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
                $query->withTrashed();
            }
            if ($class === Models\Company::class) {
                $query->where('id', $log->company_id);
            } elseif ($class === Models\User::class) {
                $query->whereHas('roles', fn ($q) => $q->where('user_roles.company_id', $log->company_id));
            } elseif (in_array($class, [Models\Role::class, Models\LeaveType::class], true)) {
                $query->where(fn ($q) => $q->where('company_id', $log->company_id)->orWhereNull('company_id'));
            } elseif (in_array('company_id', $model->getFillable(), true)) {
                $query->where('company_id', $log->company_id);
            }
            $columns = ['id', $column];
            $end = $references[$canonical][2] ?? null;
            if ($end) {
                $columns[] = $end;
            }
            foreach ($query->get($columns) as $record) {
                $label = $record->getRawOriginal($column);
                if ($end) {
                    $label .= ' to '.$record->getRawOriginal($end);
                }
                $labels[$key][(string) $record->id] = $label;
            }
        }

        return $labels;
    }
}
