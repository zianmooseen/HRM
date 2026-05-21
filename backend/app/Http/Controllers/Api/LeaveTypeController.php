<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Resources\LeaveTypeResource;
use App\Models\LeaveType;
use App\Services\Auth\CompanyAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LeaveTypeController extends Controller
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

        $leaveTypes = LeaveType::query()
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $company->id))
            ->orderByDesc('is_statutory')
            ->orderBy('name')
            ->get();

        return $this->success('Leave types retrieved.', [
            'leave_types' => LeaveTypeResource::collection($leaveTypes),
        ]);
    }
}
