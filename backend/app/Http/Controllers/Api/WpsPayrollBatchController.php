<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Payroll\UpdateWpsPayrollBatchStatusRequest;
use App\Http\Resources\WpsPayrollBatchResource;
use App\Models\PayrollPeriod;
use App\Models\WpsPayrollBatch;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CompanyAccess;
use App\Services\Payroll\WpsPayrollExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WpsPayrollBatchController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(
        private readonly CompanyAccess $access,
        private readonly AuditLogger $audit,
        private readonly WpsPayrollExportService $exports,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $this->company($request, 'payroll.export');

        $batches = $company->wpsPayrollBatches()
            ->with('payrollPeriod')
            ->when($request->query('payroll_period_id'), fn ($query, $periodId) => $query->where('payroll_period_id', $periodId))
            ->orderByDesc('generated_at')
            ->get();

        return $this->success('WPS payroll batches retrieved.', [
            'wps_payroll_batches' => WpsPayrollBatchResource::collection($batches),
        ]);
    }

    public function show(Request $request, WpsPayrollBatch $batch): JsonResponse
    {
        $company = $this->company($request, 'payroll.export');
        $this->ensureOwned($batch, $company->id);

        return $this->success('WPS payroll batch retrieved.', [
            'wps_payroll_batch' => new WpsPayrollBatchResource($batch->load(['payrollPeriod', 'items'])),
        ]);
    }

    public function generate(Request $request, PayrollPeriod $payrollPeriod): JsonResponse
    {
        $company = $this->company($request, 'payroll.export');
        abort_unless($payrollPeriod->company_id === $company->id, 403, 'You are not authorized to perform this action.');

        // Feature flow step 1: WPS export starts only after payroll approval, preserving the payroll audit trail.
        $batch = $this->exports->generate($payrollPeriod, $request->user()->id);
        $this->audit->log($request, 'wps_payroll_batch.generated', $batch, null, $batch->toArray());

        return $this->success('WPS payroll batch generated.', [
            'wps_payroll_batch' => new WpsPayrollBatchResource($batch->load(['payrollPeriod', 'items'])),
        ], 201);
    }

    public function updateStatus(UpdateWpsPayrollBatchStatusRequest $request, WpsPayrollBatch $batch): JsonResponse
    {
        $company = $this->company($request, 'payroll.export');
        $this->ensureOwned($batch, $company->id);

        $data = $request->validated();
        $before = $batch->toArray();
        $timestamps = [
            'submitted_at' => $data['status'] === 'submitted' ? now() : $batch->submitted_at,
            'accepted_at' => in_array($data['status'], ['accepted', 'partially_accepted'], true) ? now() : null,
            'rejected_at' => $data['status'] === 'rejected' ? now() : null,
        ];

        // Feature flow step 2: status tracking mirrors the external WPS submission lifecycle without calling MoHRE directly.
        $batch->update([
            'status' => $data['status'],
            'rejection_reason' => $data['status'] === 'rejected' ? ($data['rejection_reason'] ?? null) : null,
            'bank_reference' => $data['bank_reference'] ?? $batch->bank_reference,
            'response_filename' => $data['response_filename'] ?? $batch->response_filename,
            'response_details_json' => $data['response_details'] ?? $batch->response_details_json,
            'status_updated_by' => $request->user()->id,
            ...$timestamps,
        ]);
        $this->audit->log($request, 'wps_payroll_batch.status_updated', $batch, $before, $batch->fresh()->toArray());

        return $this->success('WPS payroll batch status updated.', [
            'wps_payroll_batch' => new WpsPayrollBatchResource($batch->fresh(['payrollPeriod', 'items'])),
        ]);
    }

    public function download(Request $request, WpsPayrollBatch $batch)
    {
        $company = $this->company($request, 'payroll.export');
        $this->ensureOwned($batch, $company->id);

        $extension = $batch->file_format === 'sif' ? 'sif' : 'txt';

        return response($batch->file_content ?? '', 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="'.$batch->batch_number.'.'.$extension.'"',
        ]);
    }

    private function company(Request $request, string $permission)
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, $permission);

        return $this->access->ensureCompany($user);
    }

    private function ensureOwned(WpsPayrollBatch $batch, int $companyId): void
    {
        abort_unless($batch->company_id === $companyId, 403, 'You are not authorized to perform this action.');
    }
}
