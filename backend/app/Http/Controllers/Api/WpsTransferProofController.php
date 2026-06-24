<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Payroll\StoreWpsTransferProofRequest;
use App\Http\Requests\Payroll\VerifyWpsTransferProofRequest;
use App\Http\Resources\WpsTransferProofResource;
use App\Models\WpsPayrollBatch;
use App\Models\WpsTransferProof;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CompanyAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WpsTransferProofController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(private readonly CompanyAccess $access, private readonly AuditLogger $audit)
    {
    }

    public function store(StoreWpsTransferProofRequest $request, WpsPayrollBatch $batch): JsonResponse
    {
        $company = $this->company($request, 'salary_transfers.upload_proof');
        $this->ensureOwned($batch, $company->id);
        $data = $request->validated();
        $file = $request->file('file');
        $disk = config('filesystems.default', 'local');
        $path = null;
        $hash = null;

        if ($file) {
            $extension = $file->getClientOriginalExtension() ?: $file->extension();
            $hash = hash_file('sha256', $file->getRealPath());
            $path = $file->storeAs(
                "companies/{$company->id}/payroll/{$batch->payroll_period_id}/wps-proofs",
                (string) Str::uuid().'.'.$extension,
                $disk,
            );
        }

        $proof = WpsTransferProof::query()->create([
            'company_id' => $company->id,
            'wps_payroll_batch_id' => $batch->id,
            'payroll_period_id' => $batch->payroll_period_id,
            'wps_provider_id' => $batch->wps_provider_id,
            'proof_type' => $data['proof_type'],
            'provider_reference' => $data['provider_reference'] ?? null,
            'transaction_reference' => $data['transaction_reference'] ?? null,
            'disk' => $file ? $disk : null,
            'path' => $path,
            'original_file_name' => $file?->getClientOriginalName(),
            'mime_type' => $file?->getMimeType(),
            'size_bytes' => $file?->getSize(),
            'proof_file_hash' => $hash,
            'uploaded_by' => $request->user()->id,
            'status' => 'uploaded',
            'notes' => $data['notes'] ?? null,
        ]);
        $batch->update([
            'proof_status' => 'uploaded',
            'provider_reference' => $data['provider_reference'] ?? $batch->provider_reference,
        ]);
        $this->audit->log($request, 'wps_transfer_proof.uploaded', $proof, null, $proof->toArray());

        return $this->success('WPS transfer proof saved.', [
            'wps_transfer_proof' => new WpsTransferProofResource($proof),
        ], 201);
    }

    public function verify(
        VerifyWpsTransferProofRequest $request,
        WpsTransferProof $proof,
    ): JsonResponse {
        $company = $this->company($request, 'salary_transfers.verify_proof');
        abort_unless($proof->company_id === $company->id, 403, 'You are not authorized to perform this action.');
        $before = $proof->toArray();
        $data = $request->validated();
        $proof->update([
            'status' => $data['status'],
            'notes' => $data['notes'] ?? $proof->notes,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);
        $proof->batch()->update(['proof_status' => $data['status']]);
        $this->audit->log($request, 'wps_transfer_proof.verified', $proof, $before, $proof->fresh()->toArray());

        return $this->success('WPS transfer proof reviewed.', [
            'wps_transfer_proof' => new WpsTransferProofResource($proof->fresh()),
        ]);
    }

    public function download(Request $request, WpsTransferProof $proof): StreamedResponse
    {
        $company = $this->company($request, 'salary_transfers.view');
        abort_unless($proof->company_id === $company->id, 403, 'You are not authorized to perform this action.');
        abort_unless($proof->disk && $proof->path && Storage::disk($proof->disk)->exists($proof->path), 404);

        $this->audit->log($request, 'wps_transfer_proof.downloaded', $proof);

        return Storage::disk($proof->disk)->download($proof->path, $proof->original_file_name);
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
