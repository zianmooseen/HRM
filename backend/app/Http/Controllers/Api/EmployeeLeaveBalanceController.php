<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Leave\AccrueAnnualLeaveRequest;
use App\Http\Requests\Leave\UpsertEmployeeLeaveBalanceRequest;
use App\Http\Resources\EmployeeLeaveBalanceResource;
use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveType;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CompanyAccess;
use App\Services\Leave\AnnualLeaveAccrualService;
use App\Services\Leave\LeaveBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EmployeeLeaveBalanceController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(
        private readonly CompanyAccess $access,
        private readonly LeaveBalanceService $balances,
        private readonly AnnualLeaveAccrualService $annualLeaveAccrual,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, 'leave.view');
        $company = $this->access->ensureCompany($user);
        $selfEmployeeId = null;
        $user->loadMissing('employeeRecord');
        if ($this->access->isSelfService($user)) {
            $selfEmployeeId = $this->access->employeeFor($user)?->id;
        }

        $balances = $company->leaveBalances()
            ->with(['employee', 'leaveType'])
            ->when($selfEmployeeId, fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->when($request->query('employee_id'), fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->when($request->query('leave_year'), fn ($query, $year) => $query->where('leave_year', $year))
            ->orderByDesc('leave_year')
            ->orderBy('employee_id')
            ->get();

        return $this->success('Leave balances retrieved.', [
            'leave_balances' => EmployeeLeaveBalanceResource::collection($balances),
        ]);
    }

    public function store(UpsertEmployeeLeaveBalanceRequest $request): JsonResponse
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, 'settings.update');
        $company = $this->access->ensureCompany($user);
        $data = $request->validated();
        $this->ensureEmployeeOwned((int) $data['employee_id'], $company->id);
        $this->ensureLeaveTypeVisible((int) $data['leave_type_id'], $company->id);
        $before = EmployeeLeaveBalance::query()
            ->where('company_id', $company->id)
            ->where('employee_id', $data['employee_id'])
            ->where('leave_type_id', $data['leave_type_id'])
            ->where('leave_year', $data['leave_year'])
            ->first()
            ?->toArray();

        $balance = $this->balances->upsertEntitlement([
            ...$data,
            'company_id' => $company->id,
        ]);

        $this->audit->log($request, 'leave_balance.updated', $balance, $before, [
            ...$balance->fresh()->toArray(),
            'note' => $data['note'] ?? null,
        ]);

        return $this->success('Leave balance saved.', [
            'leave_balance' => new EmployeeLeaveBalanceResource($balance->load(['employee', 'leaveType'])),
        ]);
    }

    public function accrueAnnual(AccrueAnnualLeaveRequest $request): JsonResponse
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, 'settings.update');
        $company = $this->access->ensureCompany($user);
        $data = $request->validated();

        if ($data['employee_id'] ?? null) {
            $this->ensureEmployeeOwned((int) $data['employee_id'], $company->id);
        }

        $result = $this->annualLeaveAccrual->accrue(
            $company,
            (int) $data['leave_year'],
            $data['employee_id'] ?? null,
            $data['accrual_date'] ?? null,
        );

        foreach ($result['balances'] as $balance) {
            $this->audit->log($request, 'leave_balance.accrued', $balance, null, [
                ...$balance->toArray(),
                'accrual_date' => $result['accrual_date'],
            ]);
        }

        return $this->success('Annual leave accrued.', [
            'leave_year' => $result['leave_year'],
            'accrual_date' => $result['accrual_date'],
            'processed_count' => $result['processed_count'],
            'leave_balances' => EmployeeLeaveBalanceResource::collection($result['balances']),
        ]);
    }

    private function ensureEmployeeOwned(int $employeeId, int $companyId): void
    {
        abort_unless(Employee::query()->whereKey($employeeId)->where('company_id', $companyId)->exists(), 422, 'Selected employee is invalid.');
    }

    private function ensureLeaveTypeVisible(int $leaveTypeId, int $companyId): void
    {
        abort_unless(
            LeaveType::query()
                ->whereKey($leaveTypeId)
                ->where('status', 'active')
                ->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $companyId))
                ->exists(),
            422,
            'Selected leave type is invalid.',
        );
    }
}
