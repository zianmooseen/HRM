<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Leave\ApproveLeaveRequestRequest;
use App\Http\Requests\Leave\CalculateLeaveDaysRequest;
use App\Http\Requests\Leave\RejectLeaveRequestRequest;
use App\Http\Requests\Leave\StoreLeaveRequestRequest;
use App\Http\Resources\LeavePayCalculationItemResource;
use App\Http\Resources\LeaveRequestResource;
use App\Models\Document;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CompanyAccess;
use App\Services\Leave\LeaveBalanceService;
use App\Services\Leave\LeaveDayCalculator;
use App\Services\Leave\SickLeavePayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveRequestController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(
        private readonly CompanyAccess $access,
        private readonly AuditLogger $audit,
        private readonly LeaveDayCalculator $dayCalculator,
        private readonly LeaveBalanceService $balances,
        private readonly SickLeavePayService $sickLeavePay,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $this->company($request, 'leave.view');
        $selfEmployeeId = $this->selfEmployeeId($request);

        $leaveRequests = $company->leaveRequests()
            ->with(['employee', 'leaveType', 'statusEvents'])
            ->when($selfEmployeeId, fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->when($request->query('employee_id'), fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        return $this->success('Leave requests retrieved.', [
            'leave_requests' => LeaveRequestResource::collection($leaveRequests),
        ]);
    }

    public function store(StoreLeaveRequestRequest $request): JsonResponse
    {
        $company = $this->company($request, 'leave.create');
        $data = $request->validated();
        $this->ensureSelfEmployee($request, (int) $data['employee_id']);
        $employee = $this->ensureEmployeeOwned((int) $data['employee_id'], $company->id);
        $this->ensureLeaveAllowedForEmployee($employee, $data['start_date'], $data['end_date']);
        $leaveType = $this->ensureLeaveTypeVisible((int) $data['leave_type_id'], $company->id);
        $this->ensureMedicalCertificateOwned($data, $company->id);
        $days = $this->dayCalculator->calculate($data['start_date'], $data['end_date'], $company, $employee, $leaveType);

        $leaveRequest = DB::transaction(function () use ($request, $company, $data, $days): LeaveRequest {
            // Feature flow step 1: submitted leave starts as pending and immediately reserves balance days.
            $leaveRequest = $company->leaveRequests()->create([
                ...$data,
                ...$days,
                'status' => 'pending',
                'requested_by' => $request->user()->id,
            ]);

            $this->balances->addPending($leaveRequest);
            $this->recordStatusEvent($leaveRequest, 'pending', $request->user()->id, 'Leave request submitted.');
            $this->audit->log($request, 'leave_request.created', $leaveRequest, null, $leaveRequest->toArray());

            return $leaveRequest;
        });

        return $this->success('Leave request created.', [
            'leave_request' => new LeaveRequestResource($leaveRequest->load(['employee', 'leaveType', 'payCalculationItems', 'statusEvents'])),
        ], 201);
    }

    public function dayCount(CalculateLeaveDaysRequest $request): JsonResponse
    {
        $company = $this->company($request, 'leave.view');
        $data = $request->validated();
        $this->ensureSelfEmployee($request, (int) $data['employee_id']);
        $employee = $this->ensureEmployeeOwned((int) $data['employee_id'], $company->id);
        $this->ensureLeaveAllowedForEmployee($employee, $data['start_date'], $data['end_date']);
        $leaveType = $this->ensureLeaveTypeVisible((int) $data['leave_type_id'], $company->id);

        return $this->success('Leave days calculated.', [
            'calculation' => $this->dayCalculator->calculate($data['start_date'], $data['end_date'], $company, $employee, $leaveType),
        ]);
    }

    public function show(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $company = $this->company($request, 'leave.view');
        $this->ensureOwned($leaveRequest, $company->id);
        $this->ensureSelfEmployee($request, $leaveRequest->employee_id);

        return $this->success('Leave request retrieved.', [
            'leave_request' => new LeaveRequestResource($leaveRequest->load(['employee', 'leaveType', 'payCalculationItems', 'statusEvents'])),
        ]);
    }

    public function approve(ApproveLeaveRequestRequest $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $company = $this->company($request, 'leave.approve');
        $this->ensureOwned($leaveRequest, $company->id);
        abort_unless($leaveRequest->status === 'pending', 422, 'Only pending leave requests can be approved.');

        DB::transaction(function () use ($request, $leaveRequest): void {
            // Feature flow step 2: approval moves reserved days into used days for payroll/reporting.
            $before = $leaveRequest->toArray();
            $availableDays = $this->balances->availableDaysBeforeApproval($leaveRequest);

            if (
                $this->balances->hasConfiguredEntitlement($leaveRequest)
                && $availableDays < (float) $leaveRequest->working_days
            ) {
                throw ValidationException::withMessages([
                    'leave_balance' => ['Leave request exceeds the employee available leave balance.'],
                ]);
            }

            $leaveRequest->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'approval_note' => $request->validated('approval_note'),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);
            $this->recordStatusEvent($leaveRequest->fresh(), 'approved', $request->user()->id, $request->validated('approval_note'));
            if ($leaveRequest->leaveType()->where('code', 'sick_leave')->exists()) {
                $this->sickLeavePay->storeForApprovedRequest($leaveRequest->fresh()->load(['employee', 'leaveType']));
            }
            $this->balances->approve($leaveRequest->fresh());
            $this->audit->log($request, 'leave_request.approved', $leaveRequest, $before, $leaveRequest->fresh()->toArray());
        });

        return $this->success('Leave request approved.', [
            'leave_request' => new LeaveRequestResource($leaveRequest->fresh()->load(['employee', 'leaveType', 'payCalculationItems', 'statusEvents'])),
        ]);
    }

    public function sickPay(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $company = $this->company($request, 'leave.view');
        $this->ensureOwned($leaveRequest, $company->id);
        $this->ensureSelfEmployee($request, $leaveRequest->employee_id);

        return $this->success('Sick leave pay calculated.', [
            'calculation' => $this->sickLeavePay->calculate($leaveRequest),
            'stored_items' => LeavePayCalculationItemResource::collection($leaveRequest->payCalculationItems()->get()),
        ]);
    }

    public function reject(RejectLeaveRequestRequest $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $company = $this->company($request, 'leave.reject');
        $this->ensureOwned($leaveRequest, $company->id);
        abort_unless($leaveRequest->status === 'pending', 422, 'Only pending leave requests can be rejected.');

        DB::transaction(function () use ($request, $leaveRequest): void {
            // Feature flow step 3: rejection releases pending balance and stores the reason.
            $before = $leaveRequest->toArray();
            $leaveRequest->update([
                'status' => 'rejected',
                'rejected_by' => $request->user()->id,
                'rejected_at' => now(),
                'approval_note' => null,
                'rejection_reason' => $request->validated('rejection_reason'),
            ]);
            $this->recordStatusEvent($leaveRequest->fresh(), 'rejected', $request->user()->id, $request->validated('rejection_reason'));
            $this->balances->reject($leaveRequest->fresh());
            $this->audit->log($request, 'leave_request.rejected', $leaveRequest, $before, $leaveRequest->fresh()->toArray());
        });

        return $this->success('Leave request rejected.', [
            'leave_request' => new LeaveRequestResource($leaveRequest->fresh()->load(['employee', 'leaveType', 'statusEvents'])),
        ]);
    }

    private function recordStatusEvent(LeaveRequest $leaveRequest, string $status, ?int $actorUserId, ?string $note = null): void
    {
        // Feature flow step 4: every leave status transition is kept as an approval timeline event.
        $leaveRequest->statusEvents()->create([
            'company_id' => $leaveRequest->company_id,
            'status' => $status,
            'actor_user_id' => $actorUserId,
            'note' => $note,
        ]);
    }

    private function company(Request $request, string $permission)
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, $permission);

        return $this->access->ensureCompany($user);
    }

    private function ensureOwned(LeaveRequest $leaveRequest, int $companyId): void
    {
        abort_unless($leaveRequest->company_id === $companyId, 403, 'You are not authorized to perform this action.');
    }

    private function selfEmployeeId(Request $request): ?int
    {
        $user = $request->user()->loadMissing('roles.permissions', 'employeeRecord');

        if (! $this->access->isSelfService($user)) {
            return null;
        }

        return $this->access->employeeFor($user)?->id;
    }

    private function ensureSelfEmployee(Request $request, int $employeeId): void
    {
        $selfEmployeeId = $this->selfEmployeeId($request);

        if ($selfEmployeeId === null) {
            return;
        }

        abort_unless($selfEmployeeId === $employeeId, 403, 'You are not authorized to perform this action.');
    }

    private function ensureEmployeeOwned(int $employeeId, int $companyId): Employee
    {
        $employee = Employee::query()->whereKey($employeeId)->where('company_id', $companyId)->first();

        abort_unless($employee, 422, 'Selected employee is invalid.');

        return $employee;
    }

    private function ensureLeaveAllowedForEmployee(Employee $employee, string $startDate, string $endDate): void
    {
        if ($employee->status !== 'terminated') {
            return;
        }

        if (
            ! $employee->contract_end_date
            || $employee->contract_end_date->lt($startDate)
            || $employee->contract_end_date->lt($endDate)
        ) {
            throw ValidationException::withMessages([
                'start_date' => ['Leave cannot be requested after the employee termination date.'],
            ]);
        }
    }

    private function ensureMedicalCertificateOwned(array $data, int $companyId): void
    {
        if (! ($data['medical_certificate_document_id'] ?? null)) {
            return;
        }

        $exists = Document::query()
            ->whereKey($data['medical_certificate_document_id'])
            ->where('company_id', $companyId)
            ->where('employee_id', $data['employee_id'])
            ->where('document_type', 'medical_certificate')
            ->exists();

        abort_unless($exists, 422, 'Selected medical certificate is invalid.');
    }

    private function ensureLeaveTypeVisible(int $leaveTypeId, int $companyId): LeaveType
    {
        $leaveType = LeaveType::query()
            ->whereKey($leaveTypeId)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $companyId))
            ->first();

        abort_unless(
            $leaveType,
            422,
            'Selected leave type is invalid.',
        );

        return $leaveType;
    }
}
