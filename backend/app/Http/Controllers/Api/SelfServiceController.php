<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Resources\EmployeeResource;
use App\Models\AttendanceRecord;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SelfServiceController extends Controller
{
    use RespondsWithApiEnvelope;

    public function dashboard(Request $request): JsonResponse
    {
        $today = CarbonImmutable::today();
        $employee = $request->user()
            ->employeeRecord()
            ->with(['branch', 'department', 'jobTitle'])
            ->firstOrFail();

        $todayAttendance = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $today->toDateString())
            ->first();
        $leaveRequests = $employee->leaveRequests()
            ->with('leaveType')
            ->latest('id')
            ->limit(5)
            ->get();
        $documents = $employee->documents()
            ->where('status', 'active')
            ->get();

        return $this->success('Employee dashboard retrieved.', [
            'dashboard' => [
                'employee' => new EmployeeResource($employee),
                'attendance_today' => $todayAttendance ? [
                    'status' => $todayAttendance->status,
                    'check_in' => $todayAttendance->check_in,
                    'check_out' => $todayAttendance->check_out,
                ] : null,
                'leave' => [
                    'pending_requests' => $employee->leaveRequests()->where('status', 'pending')->count(),
                    'approved_upcoming' => $employee->leaveRequests()
                        ->where('status', 'approved')
                        ->whereDate('end_date', '>=', $today->toDateString())
                        ->count(),
                    'recent_requests' => $leaveRequests->map(fn ($leaveRequest) => [
                        'id' => $leaveRequest->id,
                        'type' => $leaveRequest->leaveType?->name,
                        'start_date' => $leaveRequest->start_date?->toDateString(),
                        'end_date' => $leaveRequest->end_date?->toDateString(),
                        'status' => $leaveRequest->status,
                    ])->all(),
                ],
                'documents' => [
                    'total' => $documents->count(),
                    'expiring_soon' => $documents
                        ->filter(fn ($document) => $document->expiry_date
                            && $document->expiry_date->between($today, $today->addDays(60)))
                        ->count(),
                ],
                'pending_attendance_corrections' => $employee->attendanceCorrectionRequests()
                    ->where('status', 'pending')
                    ->count(),
            ],
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        $employee = $request->user()
            ->employeeRecord()
            ->with(['branch', 'department', 'jobTitle'])
            ->firstOrFail();

        return $this->success('Employee profile retrieved.', [
            'employee' => new EmployeeResource($employee),
        ]);
    }
}
