<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Attendance\StoreAttendanceRecordRequest;
use App\Http\Requests\Attendance\UpdateAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CompanyAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class AttendanceRecordController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(private readonly CompanyAccess $access, private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $this->company($request, 'attendance.view');

        $records = AttendanceRecord::query()
            ->with('employee')
            ->where('company_id', $company->id)
            ->when($request->query('employee_id'), fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->when($request->query('date_from'), fn ($query, $date) => $query->whereDate('date', '>=', $date))
            ->when($request->query('date_to'), fn ($query, $date) => $query->whereDate('date', '<=', $date))
            ->orderByDesc('date')
            ->orderBy('employee_id')
            ->get();

        return $this->success('Attendance records retrieved.', [
            'attendance_records' => AttendanceRecordResource::collection($records),
        ]);
    }

    public function store(StoreAttendanceRecordRequest $request): JsonResponse
    {
        $company = $this->company($request, 'attendance.create');
        $data = $this->validated($request, $company->id);

        $exists = AttendanceRecord::query()
            ->where('company_id', $company->id)
            ->where('employee_id', $data['employee_id'])
            ->whereDate('date', $data['date'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['date' => ['Attendance already exists for this employee and date.']]);
        }

        // Feature flow step 1: manual attendance entry is scoped to the current company and audit logged.
        $record = AttendanceRecord::query()->create([
            ...$data,
            'company_id' => $company->id,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $this->audit->log($request, 'attendance.created', $record, null, $record->toArray());

        return $this->success('Attendance record created.', [
            'attendance_record' => new AttendanceRecordResource($record->load('employee')),
        ], 201);
    }

    public function show(Request $request, AttendanceRecord $attendanceRecord): JsonResponse
    {
        $company = $this->company($request, 'attendance.view');
        $this->ensureOwned($attendanceRecord, $company->id);

        return $this->success('Attendance record retrieved.', [
            'attendance_record' => new AttendanceRecordResource($attendanceRecord->load('employee')),
        ]);
    }

    public function update(UpdateAttendanceRecordRequest $request, AttendanceRecord $attendanceRecord): JsonResponse
    {
        $company = $this->company($request, 'attendance.update');
        $this->ensureOwned($attendanceRecord, $company->id);
        $data = $this->validated($request, $company->id);

        $duplicate = AttendanceRecord::query()
            ->where('company_id', $company->id)
            ->where('employee_id', $data['employee_id'])
            ->whereDate('date', $data['date'])
            ->whereKeyNot($attendanceRecord->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['date' => ['Attendance already exists for this employee and date.']]);
        }

        // Feature flow step 2: every manual edit keeps before/after snapshots for HR audit review.
        $before = $attendanceRecord->toArray();
        $attendanceRecord->update([...$data, 'updated_by' => $request->user()->id]);
        $this->audit->log($request, 'attendance.updated', $attendanceRecord, $before, $attendanceRecord->fresh()->toArray());

        return $this->success('Attendance record updated.', [
            'attendance_record' => new AttendanceRecordResource($attendanceRecord->fresh()->load('employee')),
        ]);
    }

    public function destroy(Request $request, AttendanceRecord $attendanceRecord): JsonResponse
    {
        $company = $this->company($request, 'attendance.update');
        $this->ensureOwned($attendanceRecord, $company->id);

        $before = $attendanceRecord->toArray();
        $attendanceRecord->delete();
        $this->audit->log($request, 'attendance.deleted', $attendanceRecord, $before, null);

        return $this->success('Attendance record deleted.');
    }

    private function company(Request $request, string $permission)
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, $permission);

        return $this->access->ensureCompany($user);
    }

    private function validated(StoreAttendanceRecordRequest $request, int $companyId): array
    {
        $data = $request->validated();

        abort_unless(Employee::query()->whereKey($data['employee_id'])->where('company_id', $companyId)->exists(), 422, 'Selected employee is invalid.');

        $data['break_minutes'] = $data['break_minutes'] ?? 0;

        return $data;
    }

    private function ensureOwned(AttendanceRecord $record, int $companyId): void
    {
        abort_unless($record->company_id === $companyId, 403, 'You are not authorized to perform this action.');
    }
}
