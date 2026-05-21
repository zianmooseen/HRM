<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Resources\EmployeeLeaveBalanceResource;
use App\Services\Auth\CompanyAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EmployeeLeaveBalanceController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(private readonly CompanyAccess $access)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, 'leave.view');
        $company = $this->access->ensureCompany($user);

        $balances = $company->leaveBalances()
            ->with(['employee', 'leaveType'])
            ->when($request->query('employee_id'), fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->when($request->query('leave_year'), fn ($query, $year) => $query->where('leave_year', $year))
            ->orderByDesc('leave_year')
            ->orderBy('employee_id')
            ->get();

        return $this->success('Leave balances retrieved.', [
            'leave_balances' => EmployeeLeaveBalanceResource::collection($balances),
        ]);
    }
}
