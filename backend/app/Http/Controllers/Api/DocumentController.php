<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Document\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\Employee;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CompanyAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(private readonly CompanyAccess $access, private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $this->company($request, 'documents.view');
        $selfEmployeeId = $this->selfEmployeeId($request);

        $documents = Document::query()
            ->where('company_id', $company->id)
            ->when($selfEmployeeId, fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->when($request->query('employee_id'), fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->when($request->query('document_type'), fn ($query, $type) => $query->where('document_type', $type))
            ->orderByDesc('created_at')
            ->get();

        return $this->success('Documents retrieved.', [
            'documents' => DocumentResource::collection($documents),
        ]);
    }

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $company = $this->company($request, 'documents.upload');
        $data = $request->validated();
        $employee = Employee::query()
            ->whereKey($data['employee_id'])
            ->where('company_id', $company->id)
            ->firstOrFail();
        $this->ensureSelfEmployee($request, $employee->id);
        $file = $request->file('file');
        $disk = config('filesystems.default', 'local');
        $extension = $file->getClientOriginalExtension() ?: $file->extension();
        $path = $file->storeAs(
            "employee-documents/{$company->id}/{$employee->id}",
            (string) Str::uuid().'.'.$extension,
            $disk
        );

        // Feature flow step 1: uploaded files are private by default and referenced through a document row.
        $document = Document::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'document_type' => $data['document_type'],
            'title' => ($data['title'] ?? null) ?: (string) str($data['document_type'])->replace('_', ' ')->title(),
            'original_file_name' => $file->getClientOriginalName(),
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size_bytes' => $file->getSize() ?: 0,
            'issue_date' => $data['issue_date'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'uploaded_by' => $request->user()->id,
        ]);

        $this->audit->log($request, 'document.uploaded', $document, null, $document->toArray());

        return $this->success('Document uploaded.', [
            'document' => new DocumentResource($document),
        ], 201);
    }

    public function download(Request $request, Document $document): StreamedResponse
    {
        $company = $this->company($request, 'documents.view');
        $this->ensureOwned($document, $company->id);
        $this->ensureSelfEmployee($request, $document->employee_id);

        abort_unless(Storage::disk($document->disk)->exists($document->path), 404, 'Document file was not found.');

        return Storage::disk($document->disk)->download($document->path, $document->original_file_name);
    }

    public function preview(Request $request, Document $document): StreamedResponse
    {
        $company = $this->company($request, 'documents.view');
        $this->ensureOwned($document, $company->id);
        $this->ensureSelfEmployee($request, $document->employee_id);

        abort_unless(str_starts_with((string) $document->mime_type, 'image/'), 422, 'Only image documents can be previewed.');
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404, 'Document file was not found.');

        return Storage::disk($document->disk)->response($document->path, $document->original_file_name, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="'.$document->original_file_name.'"',
        ]);
    }

    public function destroy(Request $request, Document $document): JsonResponse
    {
        $company = $this->company($request, 'documents.delete');
        $this->ensureOwned($document, $company->id);

        $before = $document->toArray();
        Storage::disk($document->disk)->delete($document->path);
        $document->update(['status' => 'deleted', 'deleted_by' => $request->user()->id]);
        $document->delete();

        // Feature flow step 2: deleting a sensitive employee document removes the file and keeps an audit record.
        $this->audit->log($request, 'document.deleted', $document, $before, null);

        return $this->success('Document deleted.');
    }

    private function company(Request $request, string $permission)
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, $permission);

        return $this->access->ensureCompany($user);
    }

    private function ensureOwned(Document $document, int $companyId): void
    {
        abort_unless($document->company_id === $companyId, 403, 'You are not authorized to perform this action.');
    }

    private function selfEmployeeId(Request $request): ?int
    {
        $user = $request->user()->loadMissing('roles.permissions', 'employeeRecord');

        if (! $this->access->isSelfService($user)) {
            return null;
        }

        return $this->access->employeeFor($user)?->id;
    }

    private function ensureSelfEmployee(Request $request, int $employeeId): void
    {
        $selfEmployeeId = $this->selfEmployeeId($request);

        if ($selfEmployeeId === null) {
            return;
        }

        abort_unless($selfEmployeeId === $employeeId, 403, 'You are not authorized to perform this action.');
    }
}
