<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Resources\EmployeeResource;
use App\Models\AttendanceRecord;
use App\Models\Employee;
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

    public function company(Request $request): JsonResponse
    {
        $employee = $request->user()->employeeRecord()->with(['company', 'branch'])->firstOrFail();
        $company = $employee->company;

        return $this->success('Your company retrieved.', ['company' => [
            'name' => $company->name, 'legal_name' => $company->legal_name,
            'country' => $company->country, 'emirate' => $company->emirate,
            'timezone' => $company->timezone, 'default_currency' => $company->default_currency,
            'branch' => $employee->branch?->only(['name', 'address_line_1', 'address_line_2', 'city', 'phone', 'email']),
        ]]);
    }

    public function directory(Request $request): JsonResponse
    {
        // 1. Company membership comes from the authenticated employee, never query parameters.
        $employee = $request->user()->employeeRecord()->firstOrFail();
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $search = trim($data['search'] ?? '');
        $people = Employee::query()->where('company_id', $employee->company_id)
            ->where('status', 'active')->with(['jobTitle:id,title', 'department:id,name', 'branch:id,name,phone'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->whereRaw('LOWER(display_name) LIKE ?', ['%'.mb_strtolower($search).'%'])
                    ->orWhereRaw('LOWER(work_email) LIKE ?', ['%'.mb_strtolower($search).'%']);
            }))
            ->orderBy('display_name')->orderBy('id')
            ->paginate(25, ['id', 'display_name', 'work_email', 'job_title_id', 'department_id', 'branch_id']);

        // 2. Publish only directory fields; personal contact, salary and ID documents stay private.
        return $this->success('Employee directory retrieved.', [
            'employees' => $people->getCollection()->map(fn ($person) => [
                'id' => $person->id, 'name' => $person->display_name, 'position' => $person->jobTitle?->title,
                'department' => $person->department?->name, 'branch' => $person->branch?->name,
                'work_email' => $person->work_email, 'office_phone' => $person->branch?->phone,
            ]),
            'pagination' => ['current_page' => $people->currentPage(), 'last_page' => $people->lastPage(), 'total' => $people->total()],
        ]);
    }
}
