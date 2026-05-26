<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Onboarding\StoreOnboardingTemplateRequest;
use App\Http\Resources\OnboardingTemplateResource;
use App\Models\OnboardingTemplate;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CompanyAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class OnboardingTemplateController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(private readonly CompanyAccess $access, private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $this->company($request, 'employees.view');

        $templates = OnboardingTemplate::query()
            ->where('company_id', $company->id)
            ->with('tasks')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return $this->success('Onboarding templates retrieved.', [
            'onboarding_templates' => OnboardingTemplateResource::collection($templates),
        ]);
    }

    public function store(StoreOnboardingTemplateRequest $request): JsonResponse
    {
        $company = $this->company($request, 'employees.update');
        $data = $request->validated();

        $template = DB::transaction(function () use ($request, $company, $data): OnboardingTemplate {
            if ($data['is_default'] ?? false) {
                OnboardingTemplate::query()->where('company_id', $company->id)->update(['is_default' => false]);
            }

            $template = OnboardingTemplate::query()->create([
                'company_id' => $company->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'employment_type' => $data['employment_type'] ?? null,
                'is_default' => $data['is_default'] ?? false,
                'status' => $data['status'] ?? 'active',
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            foreach ($data['tasks'] as $index => $task) {
                $template->tasks()->create([
                    'title' => $task['title'],
                    'description' => $task['description'] ?? null,
                    'task_type' => $task['task_type'],
                    'assigned_to_role' => $task['assigned_to_role'] ?? null,
                    'required' => $task['required'] ?? true,
                    'sort_order' => $task['sort_order'] ?? $index,
                    'due_days_after_start' => $task['due_days_after_start'] ?? null,
                ]);
            }

            $this->audit->log($request, 'onboarding_template.created', $template, null, $template->load('tasks')->toArray());

            return $template;
        });

        return $this->success('Onboarding template created.', [
            'onboarding_template' => new OnboardingTemplateResource($template->fresh()->load('tasks')),
        ], 201);
    }

    private function company(Request $request, string $permission)
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, $permission);

        return $this->access->ensureCompany($user);
    }
}
