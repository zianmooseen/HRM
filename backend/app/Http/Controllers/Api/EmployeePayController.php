<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Resources\EmployeePayslipResource;
use App\Services\Payroll\EmployeePayView;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EmployeePayController extends Controller
{
    use RespondsWithApiEnvelope;

    public function overview(Request $request, EmployeePayView $pay)
    {
        $employee = $request->user()->employeeRecord()->with('company')->firstOrFail();
        $data = $request->validate(['year' => ['nullable', 'integer', 'between:1900,2100']]);
        $current = $pay->latest($employee)->first();

        return $this->success('Your pay details retrieved.', [
            ...$pay->overview($employee, $data['year'] ?? now($employee->company->timezone)->year),
            'current_statement' => $current ? new EmployeePayslipResource($current) : null,
        ]);
    }

    public function history(Request $request, EmployeePayView $pay)
    {
        $employee = $request->user()->employeeRecord()->with('company')->firstOrFail();
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $statements = $pay->latest($employee)->paginate(12);

        return $this->success('Your pay history retrieved.', [
            'currency' => $employee->company->default_currency,
            'statements' => EmployeePayslipResource::collection($statements),
            'pagination' => ['current_page' => $statements->currentPage(), 'last_page' => $statements->lastPage(), 'total' => $statements->total()],
        ]);
    }
}
