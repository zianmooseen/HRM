<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Company\StoreDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Branch;
use App\Models\Department;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CompanyAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class DepartmentController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(private readonly CompanyAccess $access, private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $this->company($request, 'companies.view');

        return $this->success('Departments retrieved.', [
            'departments' => DepartmentResource::collection($company->departments()->orderBy('name')->get()),
        ]);
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $company = $this->company($request, 'companies.update');
        $data = $this->validated($request, $company->id);
        $this->ensureUniqueCode($company->id, $data['code']);

        $department = $company->departments()->create([...$data, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        $this->audit->log($request, 'department.created', $department, null, $department->toArray());

        return $this->success('Department created.', ['department' => new DepartmentResource($department)], 201);
    }

    public function update(StoreDepartmentRequest $request, Department $department): JsonResponse
    {
        $company = $this->company($request, 'companies.update');
        $this->ensureOwned($department, $company->id);
        $data = $this->validated($request, $company->id);
        $this->ensureUniqueCode($company->id, $data['code'], $department->id);

        $before = $department->toArray();
        $department->update([...$data, 'updated_by' => $request->user()->id]);
        $this->audit->log($request, 'department.updated', $department, $before, $department->fresh()->toArray());

        return $this->success('Department updated.', ['department' => new DepartmentResource($department->fresh())]);
    }

    public function destroy(Request $request, Department $department): JsonResponse
    {
        $company = $this->company($request, 'companies.update');
        $this->ensureOwned($department, $company->id);

        $before = $department->toArray();
        $department->delete();
        $this->audit->log($request, 'department.deleted', $department, $before, null);

        return $this->success('Department deleted.');
    }

    private function company(Request $request, string $permission)
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, $permission);

        return $this->access->ensureCompany($user);
    }

    private function validated(StoreDepartmentRequest $request, int $companyId): array
    {
        $data = $request->validated();

        if ($data['branch_id'] ?? null) {
            abort_unless(Branch::query()->whereKey($data['branch_id'])->where('company_id', $companyId)->exists(), 422, 'Selected branch is invalid.');
        }

        return $data;
    }

    private function ensureOwned(Department $department, int $companyId): void
    {
        abort_unless($department->company_id === $companyId, 403, 'You are not authorized to perform this action.');
    }

    private function ensureUniqueCode(int $companyId, string $code, ?int $ignoreId = null): void
    {
        $exists = Department::query()->where('company_id', $companyId)->where('code', $code)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists();

        if ($exists) {
            throw ValidationException::withMessages(['code' => ['The department code is already used for this company.']]);
        }
    }
}
