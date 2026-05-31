<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Employee;
use App\Models\EmployeeOnboardingCase;
use App\Models\EmiratisationSnapshot;
use App\Models\LeaveRequest;
use App\Models\PayrollPeriod;
use App\Services\Auth\CompanyAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(private readonly CompanyAccess $access)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $company = $this->company($request);
        $today = CarbonImmutable::today();
        $expiryDate = $today->addDays(60);

        return $this->success('Dashboard summary retrieved.', [
            'dashboard' => [
                'employee_counts' => $this->employeeCounts($company->id),
                'attendance_today' => $this->attendanceToday($company->id, $today),
                'leave' => $this->leaveSummary($company->id),
                'payroll' => $this->payrollSummary($company->id),
                'compliance' => $this->complianceSummary($company->id),
                'alerts' => [
                    'contracts_expiring' => $this->contractsExpiring($company->id, $today, $expiryDate),
                    'documents_expiring' => $this->documentsExpiring($company->id, $today, $expiryDate),
                ],
                'recent_audit_logs' => $this->recentAuditLogs($company->id),
            ],
        ]);
    }

    private function employeeCounts(int $companyId): array
    {
        $counts = Employee::query()
            ->where('company_id', $companyId)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'active' => (int) ($counts['active'] ?? 0),
            'onboarding' => (int) ($counts['onboarding'] ?? 0),
            'terminated' => (int) ($counts['terminated'] ?? 0),
            'draft' => (int) ($counts['draft'] ?? 0),
            'total' => (int) $counts->sum(),
        ];
    }

    private function attendanceToday(int $companyId, CarbonImmutable $today): array
    {
        $counts = AttendanceRecord::query()
            ->where('company_id', $companyId)
            ->whereDate('date', $today->toDateString())
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'date' => $today->toDateString(),
            'recorded' => (int) $counts->sum(),
            'present' => (int) ($counts['present'] ?? 0),
            'late' => (int) ($counts['late'] ?? 0),
            'absent' => (int) ($counts['absent'] ?? 0),
            'on_leave' => (int) ($counts['on_leave'] ?? 0),
            'remote' => (int) ($counts['remote'] ?? 0),
        ];
    }

    private function leaveSummary(int $companyId): array
    {
        return [
            'pending_requests' => LeaveRequest::query()
                ->where('company_id', $companyId)
                ->where('status', 'pending')
                ->count(),
            'approved_this_month' => LeaveRequest::query()
                ->where('company_id', $companyId)
                ->where('status', 'approved')
                ->whereMonth('approved_at', now()->month)
                ->whereYear('approved_at', now()->year)
                ->count(),
        ];
    }

    private function payrollSummary(int $companyId): array
    {
        $period = PayrollPeriod::query()
            ->where('company_id', $companyId)
            ->orderByDesc('period_start')
            ->orderByDesc('id')
            ->first();

        return [
            'latest_period' => $period ? [
                'id' => $period->id,
                'period_start' => $period->period_start?->toDateString(),
                'period_end' => $period->period_end?->toDateString(),
                'pay_date' => $period->pay_date?->toDateString(),
                'status' => $period->status,
            ] : null,
        ];
    }

    private function complianceSummary(int $companyId): array
    {
        $snapshot = EmiratisationSnapshot::query()
            ->where('company_id', $companyId)
            ->latest('snapshot_date')
            ->latest('id')
            ->first();

        return [
            'latest_emiratisation_snapshot' => $snapshot ? [
                'snapshot_date' => $snapshot->snapshot_date?->toDateString(),
                'compliance_status' => $snapshot->compliance_status,
                'required_uae_citizens' => $snapshot->required_uae_citizens,
                'missing_uae_citizens' => $snapshot->missing_uae_citizens,
                'estimated_contribution_amount' => $snapshot->estimated_contribution_amount,
            ] : null,
        ];
    }

    private function contractsExpiring(int $companyId, CarbonImmutable $today, CarbonImmutable $expiryDate): array
    {
        return Employee::query()
            ->where('company_id', $companyId)
            ->whereIn('status', ['active', 'onboarding', 'on_leave', 'suspended'])
            ->whereNotNull('contract_end_date')
            ->whereBetween('contract_end_date', [$today->toDateString(), $expiryDate->toDateString()])
            ->orderBy('contract_end_date')
            ->limit(8)
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'display_name' => $employee->display_name,
                'contract_end_date' => $employee->contract_end_date?->toDateString(),
                'days_remaining' => $employee->contract_end_date
                    ? $today->diffInDays(CarbonImmutable::parse($employee->contract_end_date), false)
                    : null,
            ])
            ->all();
    }

    private function documentsExpiring(int $companyId, CarbonImmutable $today, CarbonImmutable $expiryDate): array
    {
        return Document::query()
            ->with('employee')
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [$today->toDateString(), $expiryDate->toDateString()])
            ->orderBy('expiry_date')
            ->limit(8)
            ->get()
            ->map(fn (Document $document) => [
                'id' => $document->id,
                'employee_id' => $document->employee_id,
                'employee_name' => $document->employee?->display_name,
                'title' => $document->title,
                'document_type' => $document->document_type,
                'expiry_date' => $document->expiry_date?->toDateString(),
                'days_remaining' => $document->expiry_date
                    ? $today->diffInDays(CarbonImmutable::parse($document->expiry_date), false)
                    : null,
            ])
            ->all();
    }

    private function recentAuditLogs(int $companyId): array
    {
        return AuditLog::query()
            ->where('company_id', $companyId)
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (AuditLog $log) => [
                'created_at' => optional($log->created_at)->toDateTimeString(),
                'action' => $log->action,
                'auditable_type' => class_basename($log->auditable_type),
                'auditable_id' => $log->auditable_id,
                'actor_user_id' => $log->actor_user_id,
            ])
            ->all();
    }

    private function company(Request $request)
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, 'employees.view');

        return $this->access->ensureCompany($user);
    }
}
