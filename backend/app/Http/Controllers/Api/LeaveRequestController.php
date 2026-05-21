<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Leave\RejectLeaveRequestRequest;
use App\Http\Requests\Leave\StoreLeaveRequestRequest;
use App\Http\Resources\LeaveRequestResource;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CompanyAccess;
use App\Services\Leave\LeaveBalanceService;
use App\Services\Leave\LeaveDayCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class LeaveRequestController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(
        private readonly CompanyAccess $access,
        private readonly AuditLogger $audit,
        private readonly LeaveDayCalculator $dayCalculator,
        private readonly LeaveBalanceService $balances,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $this->company($request, 'leave.view');

        $leaveRequests = $company->leaveRequests()
            ->with(['employee', 'leaveType'])
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
        $this->ensureEmployeeOwned((int) $data['employee_id'], $company->id);
        $this->ensureLeaveTypeVisible((int) $data['leave_type_id'], $company->id);
        $days = $this->dayCalculator->calculate($data['start_date'], $data['end_date']);

        $leaveRequest = DB::transaction(function () use ($request, $company, $data, $days): LeaveRequest {
            // Feature flow step 1: submitted leave starts as pending and immediately reserves balance days.
            $leaveRequest = $company->leaveRequests()->create([
                ...$data,
                ...$days,
                'status' => 'pending',
                'requested_by' => $request->user()->id,
            ]);

            $this->balances->addPending($leaveRequest);
            $this->audit->log($request, 'leave_request.created', $leaveRequest, null, $leaveRequest->toArray());

            return $leaveRequest;
        });

        return $this->success('Leave request created.', [
            'leave_request' => new LeaveRequestResource($leaveRequest->load(['employee', 'leaveType'])),
        ], 201);
    }

    public function show(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $company = $this->company($request, 'leave.view');
        $this->ensureOwned($leaveRequest, $company->id);

        return $this->success('Leave request retrieved.', [
            'leave_request' => new LeaveRequestResource($leaveRequest->load(['employee', 'leaveType'])),
        ]);
    }

    public function approve(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $company = $this->company($request, 'leave.approve');
        $this->ensureOwned($leaveRequest, $company->id);
        abort_unless($leaveRequest->status === 'pending', 422, 'Only pending leave requests can be approved.');

        DB::transaction(function () use ($request, $leaveRequest): void {
            // Feature flow step 2: approval moves reserved days into used days for payroll/reporting.
            $before = $leaveRequest->toArray();
            $leaveRequest->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);
            $this->balances->approve($leaveRequest->fresh());
            $this->audit->log($request, 'leave_request.approved', $leaveRequest, $before, $leaveRequest->fresh()->toArray());
        });

        return $this->success('Leave request approved.', [
            'leave_request' => new LeaveRequestResource($leaveRequest->fresh()->load(['employee', 'leaveType'])),
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
                'rejection_reason' => $request->validated('rejection_reason'),
            ]);
            $this->balances->reject($leaveRequest->fresh());
            $this->audit->log($request, 'leave_request.rejected', $leaveRequest, $before, $leaveRequest->fresh()->toArray());
        });

        return $this->success('Leave request rejected.', [
            'leave_request' => new LeaveRequestResource($leaveRequest->fresh()->load(['employee', 'leaveType'])),
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
