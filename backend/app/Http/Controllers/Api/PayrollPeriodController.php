<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Payroll\StorePayrollPeriodRequest;
use App\Http\Resources\PayrollPeriodResource;
use App\Http\Resources\PayslipResource;
use App\Models\PayrollPeriod;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CompanyAccess;
use App\Services\Payroll\PayrollRunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PayrollPeriodController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(
        private readonly CompanyAccess $access,
        private readonly AuditLogger $audit,
        private readonly PayrollRunService $payroll,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $this->company($request, 'payroll.view');

        $periods = $company->payrollPeriods()
            ->withCount('payslips')
            ->orderByDesc('period_start')
            ->get();

        return $this->success('Payroll periods retrieved.', [
            'payroll_periods' => PayrollPeriodResource::collection($periods),
        ]);
    }

    public function store(StorePayrollPeriodRequest $request): JsonResponse
    {
        $company = $this->company($request, 'payroll.run');

        $period = $company->payrollPeriods()->create([
            ...$request->validated(),
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);
        $this->audit->log($request, 'payroll_period.created', $period, null, $period->toArray());

        return $this->success('Payroll period created.', [
            'payroll_period' => new PayrollPeriodResource($period->loadCount('payslips')),
        ], 201);
    }

    public function show(Request $request, PayrollPeriod $payrollPeriod): JsonResponse
    {
        $company = $this->company($request, 'payroll.view');
        $this->ensureOwned($payrollPeriod, $company->id);

        return $this->success('Payroll period retrieved.', [
            'payroll_period' => new PayrollPeriodResource($payrollPeriod->loadCount('payslips')),
            'payslips' => PayslipResource::collection($payrollPeriod->payslips()->with(['employee', 'items'])->orderBy('employee_id')->get()),
        ]);
    }

    public function run(Request $request, PayrollPeriod $payrollPeriod): JsonResponse
    {
        $company = $this->company($request, 'payroll.run');
        $this->ensureOwned($payrollPeriod, $company->id);
        abort_unless($payrollPeriod->status !== 'approved', 422, 'Approved payroll periods cannot be rerun.');

        $before = $payrollPeriod->toArray();
        $count = $this->payroll->run($payrollPeriod->load('company'));
        $this->audit->log($request, 'payroll_period.run', $payrollPeriod, $before, $payrollPeriod->fresh()->toArray());

        return $this->success('Payroll run completed.', [
            'payroll_period' => new PayrollPeriodResource($payrollPeriod->fresh()->loadCount('payslips')),
            'payslips_created' => $count,
        ]);
    }

    public function approve(Request $request, PayrollPeriod $payrollPeriod): JsonResponse
    {
        $company = $this->company($request, 'payroll.approve');
        $this->ensureOwned($payrollPeriod, $company->id);
        abort_unless($payrollPeriod->status === 'processed', 422, 'Only processed payroll periods can be approved.');

        // Feature flow step 3: approval locks the payroll period and marks generated payslips approved.
        $before = $payrollPeriod->toArray();
        $payrollPeriod->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);
        $payrollPeriod->payslips()->update(['status' => 'approved']);
        $this->audit->log($request, 'payroll_period.approved', $payrollPeriod, $before, $payrollPeriod->fresh()->toArray());

        return $this->success('Payroll period approved.', [
            'payroll_period' => new PayrollPeriodResource($payrollPeriod->fresh()->loadCount('payslips')),
        ]);
    }

    private function company(Request $request, string $permission)
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, $permission);

        return $this->access->ensureCompany($user);
    }

    private function ensureOwned(PayrollPeriod $period, int $companyId): void
    {
        abort_unless($period->company_id === $companyId, 403, 'You are not authorized to perform this action.');
    }
}
