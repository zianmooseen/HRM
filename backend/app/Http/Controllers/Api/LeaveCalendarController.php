<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Leave\LeaveCalendarRequest;
use App\Http\Resources\LeaveCalendarEntryResource;
use App\Services\Auth\CompanyAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class LeaveCalendarController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __invoke(LeaveCalendarRequest $request, CompanyAccess $access): JsonResponse
    {
        // Feature flow step 1: authorize the current company before sharing colleague absences.
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $company = $access->ensureCompany($user);
        abort_unless($user->roles->contains(fn ($role) => (int) $role->pivot->company_id === $company->id
            && $role->permissions->contains('slug', 'leave.view')
        ), 403, 'You are not authorized to perform this action.');

        if ($access->isSelfService($user)) {
            abort_unless($access->employeeFor($user)?->company_id === $company->id, 403, 'No employee profile is assigned to this company.');
        }

        $month = $request->validated('month');
        $start = CarbonImmutable::createFromFormat('!Y-m', $month);

        // Feature flow step 2: include every approved interval overlapping the requested month.
        $entries = $company->leaveRequests()
            ->select(['id', 'employee_id', 'start_date', 'end_date'])
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $start->endOfMonth()->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->whereHas('employee', fn ($query) => $query->where('company_id', $company->id))
            ->with('employee:id,user_id,display_name,first_name,last_name')
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();

        // Feature flow step 3: expose only the calendar's name/date allowlist.
        return $this->success('Leave calendar retrieved.', [
            'month' => $month,
            'entries' => LeaveCalendarEntryResource::collection($entries),
        ]);
    }
}
