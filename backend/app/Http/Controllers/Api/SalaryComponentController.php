<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Payroll\StoreSalaryComponentRequest;
use App\Http\Resources\SalaryComponentResource;
use App\Models\SalaryComponent;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CompanyAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class SalaryComponentController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(private readonly CompanyAccess $access, private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $this->company($request, 'payroll.view');

        return $this->success('Salary components retrieved.', [
            'salary_components' => SalaryComponentResource::collection($company->salaryComponents()->orderBy('name')->get()),
        ]);
    }

    public function store(StoreSalaryComponentRequest $request): JsonResponse
    {
        $company = $this->company($request, 'payroll.run');
        $this->ensureUniqueCode($company->id, $request->validated('code'));

        $component = $company->salaryComponents()->create([
            ...$request->validated(),
            'is_taxable' => $request->boolean('is_taxable'),
            'is_recurring' => $request->boolean('is_recurring', true),
        ]);

        $this->audit->log($request, 'salary_component.created', $component, null, $component->toArray());

        return $this->success('Salary component created.', [
            'salary_component' => new SalaryComponentResource($component),
        ], 201);
    }

    public function update(StoreSalaryComponentRequest $request, SalaryComponent $salaryComponent): JsonResponse
    {
        $company = $this->company($request, 'payroll.run');
        $this->ensureOwned($salaryComponent, $company->id);
        $this->ensureUniqueCode($company->id, $request->validated('code'), $salaryComponent->id);

        $before = $salaryComponent->toArray();
        $salaryComponent->update([
            ...$request->validated(),
            'is_taxable' => $request->boolean('is_taxable'),
            'is_recurring' => $request->boolean('is_recurring', true),
        ]);
        $this->audit->log($request, 'salary_component.updated', $salaryComponent, $before, $salaryComponent->fresh()->toArray());

        return $this->success('Salary component updated.', [
            'salary_component' => new SalaryComponentResource($salaryComponent->fresh()),
        ]);
    }

    private function company(Request $request, string $permission)
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, $permission);

        return $this->access->ensureCompany($user);
    }

    private function ensureOwned(SalaryComponent $component, int $companyId): void
    {
        abort_unless($component->company_id === $companyId, 403, 'You are not authorized to perform this action.');
    }

    private function ensureUniqueCode(int $companyId, string $code, ?int $ignoreId = null): void
    {
        $exists = SalaryComponent::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['code' => ['The salary component code is already used for this company.']]);
        }
    }
}
