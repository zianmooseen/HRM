<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Company\StoreBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CompanyAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class BranchController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(
        private readonly CompanyAccess $access,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $this->company($request, 'companies.view');

        return $this->success('Branches retrieved.', [
            'branches' => BranchResource::collection($company->branches()->orderBy('name')->get()),
        ]);
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        $company = $this->company($request, 'companies.update');

        $this->ensureUniqueCode($company->id, $request->validated('code'));

        $branch = $company->branches()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $this->audit->log($request, 'branch.created', $branch, null, $branch->toArray());

        return $this->success('Branch created.', [
            'branch' => new BranchResource($branch),
        ], 201);
    }

    public function update(StoreBranchRequest $request, Branch $branch): JsonResponse
    {
        $company = $this->company($request, 'companies.update');
        $this->ensureOwned($branch, $company->id);
        $this->ensureUniqueCode($company->id, $request->validated('code'), $branch->id);

        $before = $branch->toArray();
        $branch->update([...$request->validated(), 'updated_by' => $request->user()->id]);

        $this->audit->log($request, 'branch.updated', $branch, $before, $branch->fresh()->toArray());

        return $this->success('Branch updated.', [
            'branch' => new BranchResource($branch->fresh()),
        ]);
    }

    public function destroy(Request $request, Branch $branch): JsonResponse
    {
        $company = $this->company($request, 'companies.update');
        $this->ensureOwned($branch, $company->id);

        $before = $branch->toArray();
        $branch->delete();
        $this->audit->log($request, 'branch.deleted', $branch, $before, null);

        return $this->success('Branch deleted.');
    }

    private function company(Request $request, string $permission)
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, $permission);

        return $this->access->ensureCompany($user);
    }

    private function ensureOwned(Branch $branch, int $companyId): void
    {
        abort_unless($branch->company_id === $companyId, 403, 'You are not authorized to perform this action.');
    }

    private function ensureUniqueCode(int $companyId, string $code, ?int $ignoreId = null): void
    {
        $exists = Branch::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['code' => ['The branch code is already used for this company.']]);
        }
    }
}
