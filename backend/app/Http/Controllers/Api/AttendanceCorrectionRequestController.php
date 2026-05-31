<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Attendance\RejectAttendanceCorrectionRequest;
use App\Http\Requests\Attendance\StoreAttendanceCorrectionRequest;
use App\Http\Resources\AttendanceCorrectionRequestResource;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CompanyAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class AttendanceCorrectionRequestController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(private readonly CompanyAccess $access, private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $this->company($request, 'attendance.view');
        $selfEmployeeId = $this->selfEmployeeId($request);

        $corrections = AttendanceCorrectionRequest::query()
            ->with(['employee', 'attendanceRecord'])
            ->where('company_id', $company->id)
            ->when($selfEmployeeId, fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->when($request->query('employee_id') && ! $selfEmployeeId, fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return $this->success('Attendance correction requests retrieved.', [
            'attendance_correction_requests' => AttendanceCorrectionRequestResource::collection($corrections),
        ]);
    }

    public function store(StoreAttendanceCorrectionRequest $request): JsonResponse
    {
        $company = $this->company($request, 'attendance.view');
        $data = $request->validated();
        $selfEmployeeId = $this->selfEmployeeId($request);
        $employeeId = $selfEmployeeId ?: ($data['employee_id'] ?? null);

        if (! $employeeId) {
            throw ValidationException::withMessages(['employee_id' => ['Employee is required.']]);
        }

        $employee = Employee::query()
            ->whereKey($employeeId)
            ->where('company_id', $company->id)
            ->first();

        abort_unless($employee, 422, 'Selected employee is invalid.');

        if ($selfEmployeeId) {
            abort_unless((int) $selfEmployeeId === (int) $employee->id, 403, 'You are not authorized to perform this action.');
        }

        $attendanceRecord = $this->attendanceRecordForRequest($company->id, $employee->id, $data);

        // Feature flow step 1: corrections start as pending requests and do not mutate attendance until approval.
        $correction = AttendanceCorrectionRequest::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'attendance_record_id' => $attendanceRecord?->id,
            'date' => $data['date'],
            'correction_type' => $data['correction_type'],
            'requested_check_in' => $data['requested_check_in'] ?? null,
            'requested_check_out' => $data['requested_check_out'] ?? null,
            'requested_break_minutes' => $data['requested_break_minutes'] ?? 0,
            'requested_status' => $data['requested_status'],
            'reason' => $data['reason'],
            'status' => 'pending',
            'requested_by' => $request->user()->id,
        ]);

        $this->audit->log($request, 'attendance_correction.submitted', $correction, null, $correction->toArray());

        return $this->success('Attendance correction request submitted.', [
            'attendance_correction_request' => new AttendanceCorrectionRequestResource($correction->load(['employee', 'attendanceRecord'])),
        ], 201);
    }

    public function approve(Request $request, AttendanceCorrectionRequest $correction): JsonResponse
    {
        $company = $this->company($request, 'attendance.approve');
        $this->ensureOwned($correction, $company->id);

        abort_unless($correction->status === 'pending', 422, 'Only pending correction requests can be approved.');

        $beforeCorrection = $correction->toArray();
        $record = $this->applyCorrection($request, $correction);

        $correction->update([
            'attendance_record_id' => $record->id,
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $this->audit->log($request, 'attendance_correction.approved', $correction, $beforeCorrection, $correction->fresh()->toArray());

        return $this->success('Attendance correction request approved.', [
            'attendance_correction_request' => new AttendanceCorrectionRequestResource($correction->fresh()->load(['employee', 'attendanceRecord'])),
        ]);
    }

    public function reject(RejectAttendanceCorrectionRequest $request, AttendanceCorrectionRequest $correction): JsonResponse
    {
        $company = $this->company($request, 'attendance.approve');
        $this->ensureOwned($correction, $company->id);

        abort_unless($correction->status === 'pending', 422, 'Only pending correction requests can be rejected.');

        $before = $correction->toArray();
        $correction->update([
            'status' => 'rejected',
            'rejected_by' => $request->user()->id,
            'rejected_at' => now(),
            'rejection_reason' => $request->validated('rejection_reason'),
        ]);

        $this->audit->log($request, 'attendance_correction.rejected', $correction, $before, $correction->fresh()->toArray());

        return $this->success('Attendance correction request rejected.', [
            'attendance_correction_request' => new AttendanceCorrectionRequestResource($correction->fresh()->load(['employee', 'attendanceRecord'])),
        ]);
    }

    private function applyCorrection(Request $request, AttendanceCorrectionRequest $correction): AttendanceRecord
    {
        $record = $correction->attendanceRecord;
        $action = 'attendance.updated';

        if (! $record) {
            $record = AttendanceRecord::query()
                ->where('company_id', $correction->company_id)
                ->where('employee_id', $correction->employee_id)
                ->whereDate('date', $correction->date)
                ->first();
        }

        $before = $record?->toArray();

        if (! $record) {
            $action = 'attendance.created';
            $record = new AttendanceRecord([
                'company_id' => $correction->company_id,
                'employee_id' => $correction->employee_id,
                'date' => $correction->date,
                'source' => 'correction',
                'created_by' => $request->user()->id,
            ]);
        }

        $record->fill([
            'check_in' => $correction->requested_check_in,
            'check_out' => $correction->requested_check_out,
            'break_minutes' => $correction->requested_break_minutes,
            'status' => $correction->requested_status,
            'source' => 'correction',
            'notes' => $correction->reason,
            'updated_by' => $request->user()->id,
        ]);
        $record->save();

        $this->audit->log($request, $action, $record, $before, $record->fresh()->toArray());

        return $record;
    }

    private function attendanceRecordForRequest(int $companyId, int $employeeId, array $data): ?AttendanceRecord
    {
        if ($data['attendance_record_id'] ?? null) {
            $record = AttendanceRecord::query()
                ->whereKey($data['attendance_record_id'])
                ->where('company_id', $companyId)
                ->where('employee_id', $employeeId)
                ->first();

            abort_unless($record, 422, 'Selected attendance record is invalid.');

            return $record;
        }

        return AttendanceRecord::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereDate('date', $data['date'])
            ->first();
    }

    private function company(Request $request, string $permission)
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, $permission);

        return $this->access->ensureCompany($user);
    }

    private function selfEmployeeId(Request $request): ?int
    {
        $user = $request->user()->loadMissing('roles.permissions', 'employeeRecord');

        if (! $this->access->isSelfService($user)) {
            return null;
        }

        return $this->access->employeeFor($user)?->id;
    }

    private function ensureOwned(AttendanceCorrectionRequest $correction, int $companyId): void
    {
        abort_unless((int) $correction->company_id === $companyId, 403, 'You are not authorized to perform this action.');
    }
}
