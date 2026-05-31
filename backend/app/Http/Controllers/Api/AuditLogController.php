<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Services\Auth\CompanyAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuditLogController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(private readonly CompanyAccess $access)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $this->company($request);
        $user = $request->user();
        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);

        $logs = AuditLog::query()
            ->where('company_id', $company->id)
            ->when($request->query('action'), fn ($query, $action) => $query->where('action', 'like', '%'.$action.'%'))
            ->when($request->query('module'), fn ($query, $module) => $query->where('action', 'like', $module.'.%'))
            ->when($request->query('actor_user_id'), fn ($query, $actorId) => $query->where('actor_user_id', $actorId))
            ->when($request->query('date_from'), fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($request->query('date_to'), fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->when($request->query('employee_id'), function ($query, $employeeId): void {
                $query->where(function ($inner) use ($employeeId): void {
                    $inner
                        ->where(function ($employeeQuery) use ($employeeId): void {
                            $employeeQuery
                                ->where('auditable_type', Employee::class)
                                ->where('auditable_id', $employeeId);
                        })
                        ->orWhere('before_json->employee_id', $employeeId)
                        ->orWhere('after_json->employee_id', $employeeId);
                });
            })
            ->latest('id')
            ->paginate($perPage);

        return $this->success('Audit logs retrieved.', [
            'audit_logs' => $logs->getCollection()
                ->map(fn (AuditLog $log) => $this->logRow($log, $user, includeSnapshots: false))
                ->all(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
            'filters' => [
                'modules' => ['employee', 'employee_contract', 'employee_termination', 'document', 'attendance', 'leave_request', 'leave_balance', 'payroll_period', 'public_holiday', 'company_compliance_settings', 'emiratisation_snapshot'],
            ],
        ]);
    }

    public function show(Request $request, AuditLog $auditLog): JsonResponse
    {
        $company = $this->company($request);
        abort_unless((int) $auditLog->company_id === (int) $company->id, 404);

        return $this->success('Audit log retrieved.', [
            'audit_log' => $this->logRow($auditLog, $request->user(), includeSnapshots: true),
        ]);
    }

    private function logRow(AuditLog $log, $user, bool $includeSnapshots): array
    {
        $canViewSnapshots = $includeSnapshots && $this->canViewSnapshots($log, $user);

        return [
            'id' => $log->id,
            'company_id' => $log->company_id,
            'actor_user_id' => $log->actor_user_id,
            'action' => $log->action,
            'module' => $this->moduleFromAction($log->action),
            'auditable_type' => class_basename($log->auditable_type),
            'auditable_id' => $log->auditable_id,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'created_at' => optional($log->created_at)->toIso8601String(),
            'snapshots_visible' => $canViewSnapshots,
            'before' => $canViewSnapshots ? $log->before_json : null,
            'after' => $canViewSnapshots ? $log->after_json : null,
        ];
    }

    private function canViewSnapshots(AuditLog $log, $user): bool
    {
        if (str_starts_with($log->action, 'payroll_') || str_starts_with($log->action, 'employee_salary_component.') || str_starts_with($log->action, 'salary_component.')) {
            return $user->hasPermission('payroll.view');
        }

        if (str_contains($log->action, 'salary')) {
            return $user->hasPermission('employees.view_salary');
        }

        if (str_starts_with($log->action, 'document.')) {
            return $user->hasPermission('documents.view');
        }

        return true;
    }

    private function moduleFromAction(string $action): string
    {
        return str($action)->before('.')->toString();
    }

    private function company(Request $request)
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, 'audit_logs.view');

        return $this->access->ensureCompany($user);
    }
}
