<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Company\StoreJobTitleRequest;
use App\Http\Resources\JobTitleResource;
use App\Models\JobTitle;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CompanyAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class JobTitleController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(private readonly CompanyAccess $access, private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $this->company($request, 'companies.view');

        return $this->success('Job titles retrieved.', [
            'job_titles' => JobTitleResource::collection($company->jobTitles()->orderBy('title')->get()),
        ]);
    }

    public function store(StoreJobTitleRequest $request): JsonResponse
    {
        $company = $this->company($request, 'companies.update');
        $this->ensureUniqueCode($company->id, $request->validated('code'));

        $jobTitle = $company->jobTitles()->create([...$request->validated(), 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        $this->audit->log($request, 'job_title.created', $jobTitle, null, $jobTitle->toArray());

        return $this->success('Job title created.', ['job_title' => new JobTitleResource($jobTitle)], 201);
    }

    public function update(StoreJobTitleRequest $request, JobTitle $jobTitle): JsonResponse
    {
        $company = $this->company($request, 'companies.update');
        $this->ensureOwned($jobTitle, $company->id);
        $this->ensureUniqueCode($company->id, $request->validated('code'), $jobTitle->id);

        $before = $jobTitle->toArray();
        $jobTitle->update([...$request->validated(), 'updated_by' => $request->user()->id]);
        $this->audit->log($request, 'job_title.updated', $jobTitle, $before, $jobTitle->fresh()->toArray());

        return $this->success('Job title updated.', ['job_title' => new JobTitleResource($jobTitle->fresh())]);
    }

    public function destroy(Request $request, JobTitle $jobTitle): JsonResponse
    {
        $company = $this->company($request, 'companies.update');
        $this->ensureOwned($jobTitle, $company->id);

        $before = $jobTitle->toArray();
        $jobTitle->delete();
        $this->audit->log($request, 'job_title.deleted', $jobTitle, $before, null);

        return $this->success('Job title deleted.');
    }

    private function company(Request $request, string $permission)
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, $permission);

        return $this->access->ensureCompany($user);
    }

    private function ensureOwned(JobTitle $jobTitle, int $companyId): void
    {
        abort_unless($jobTitle->company_id === $companyId, 403, 'You are not authorized to perform this action.');
    }

    private function ensureUniqueCode(int $companyId, string $code, ?int $ignoreId = null): void
    {
        $exists = JobTitle::query()->where('company_id', $companyId)->where('code', $code)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists();

        if ($exists) {
            throw ValidationException::withMessages(['code' => ['The job title code is already used for this company.']]);
        }
    }
}
