<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Resources\EmployeeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SelfServiceController extends Controller
{
    use RespondsWithApiEnvelope;

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
