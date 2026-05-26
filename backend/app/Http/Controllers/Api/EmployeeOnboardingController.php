<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Onboarding\StartOnboardingRequest;
use App\Http\Requests\Onboarding\UpdateOnboardingTaskRequest;
use App\Http\Resources\EmployeeOnboardingCaseResource;
use App\Models\Employee;
use App\Models\EmployeeOnboardingCase;
use App\Models\EmployeeOnboardingTask;
use App\Models\OnboardingTemplate;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CompanyAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeOnboardingController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(private readonly CompanyAccess $access, private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $this->company($request, 'employees.view');

        $cases = EmployeeOnboardingCase::query()
            ->where('company_id', $company->id)
            ->with(['employee', 'template', 'tasks'])
            ->when($request->query('employee_id'), fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get();

        return $this->success('Onboarding cases retrieved.', [
            'onboarding_cases' => EmployeeOnboardingCaseResource::collection($cases),
        ]);
    }

    public function start(StartOnboardingRequest $request, Employee $employee): JsonResponse
    {
        $company = $this->company($request, 'employees.update');
        $this->ensureEmployeeOwned($employee, $company->id);
        $template = OnboardingTemplate::query()
            ->whereKey($request->validated('onboarding_template_id'))
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->with('tasks')
            ->firstOrFail();

        if ($template->tasks->isEmpty()) {
            throw ValidationException::withMessages(['onboarding_template_id' => ['Selected onboarding template has no tasks.']]);
        }

        $activeCaseExists = EmployeeOnboardingCase::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', ['draft', 'in_progress', 'waiting_for_employee', 'waiting_for_hr', 'waiting_for_payroll'])
            ->exists();

        if ($activeCaseExists) {
            throw ValidationException::withMessages(['employee_id' => ['Employee already has an active onboarding case.']]);
        }

        $case = DB::transaction(function () use ($request, $company, $employee, $template): EmployeeOnboardingCase {
            $case = EmployeeOnboardingCase::query()->create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'onboarding_template_id' => $template->id,
                'status' => 'in_progress',
                'started_at' => now(),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            foreach ($template->tasks as $task) {
                $case->tasks()->create([
                    'company_id' => $company->id,
                    'employee_id' => $employee->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'task_type' => $task->task_type,
                    'assigned_to_role' => $task->assigned_to_role,
                    'required' => $task->required,
                    'status' => 'pending',
                    'due_date' => $task->due_days_after_start === null ? null : now()->addDays($task->due_days_after_start)->toDateString(),
                ]);
            }

            $before = $employee->toArray();
            $employee->update(['status' => 'onboarding', 'updated_by' => $request->user()->id]);
            $this->audit->log($request, 'employee.onboarding_started', $employee, $before, $employee->fresh()->toArray());
            $this->audit->log($request, 'onboarding_case.started', $case, null, $case->load('tasks')->toArray());

            return $case;
        });

        return $this->success('Onboarding started.', [
            'onboarding_case' => new EmployeeOnboardingCaseResource($case->fresh()->load(['employee', 'template', 'tasks'])),
        ], 201);
    }

    public function updateTask(UpdateOnboardingTaskRequest $request, EmployeeOnboardingTask $task): JsonResponse
    {
        $company = $this->company($request, 'employees.update');
        abort_unless($task->company_id === $company->id, 403, 'You are not authorized to perform this action.');

        $before = $task->toArray();
        $status = $request->validated('status');
        $task->update([
            'status' => $status,
            'completed_at' => $status === 'completed' ? now() : null,
            'completed_by' => $status === 'completed' ? $request->user()->id : null,
        ]);

        $case = $task->case()->with('tasks')->firstOrFail();
        $case->update(['status' => $this->caseStatusFromTasks($case), 'updated_by' => $request->user()->id]);
        $this->audit->log($request, 'onboarding_task.updated', $task, $before, $task->fresh()->toArray());

        return $this->success('Onboarding task updated.', [
            'onboarding_case' => new EmployeeOnboardingCaseResource($case->fresh()->load(['employee', 'template', 'tasks'])),
        ]);
    }

    public function complete(Request $request, EmployeeOnboardingCase $case): JsonResponse
    {
        $company = $this->company($request, 'employees.update');
        abort_unless($case->company_id === $company->id, 403, 'You are not authorized to perform this action.');
        $case->load(['tasks', 'employee']);

        $openRequiredTasks = $case->tasks
            ->where('required', true)
            ->whereNotIn('status', ['completed', 'skipped'])
            ->count();

        if ($openRequiredTasks > 0) {
            throw ValidationException::withMessages(['tasks' => ['Complete or skip all required onboarding tasks before activation.']]);
        }

        DB::transaction(function () use ($request, $case): void {
            $beforeCase = $case->toArray();
            $case->update([
                'status' => 'completed',
                'completed_at' => now(),
                'updated_by' => $request->user()->id,
            ]);

            $employee = $case->employee;
            $beforeEmployee = $employee->toArray();
            $employee->update(['status' => 'active', 'updated_by' => $request->user()->id]);

            $this->audit->log($request, 'onboarding_case.completed', $case, $beforeCase, $case->fresh()->toArray());
            $this->audit->log($request, 'employee.activated_from_onboarding', $employee, $beforeEmployee, $employee->fresh()->toArray());
        });

        return $this->success('Onboarding completed.', [
            'onboarding_case' => new EmployeeOnboardingCaseResource($case->fresh()->load(['employee', 'template', 'tasks'])),
        ]);
    }

    private function company(Request $request, string $permission)
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, $permission);

        return $this->access->ensureCompany($user);
    }

    private function ensureEmployeeOwned(Employee $employee, int $companyId): void
    {
        abort_unless($employee->company_id === $companyId, 403, 'You are not authorized to perform this action.');
    }

    private function caseStatusFromTasks(EmployeeOnboardingCase $case): string
    {
        if ($case->tasks->where('status', 'blocked')->isNotEmpty()) {
            return 'waiting_for_hr';
        }

        if ($case->tasks->where('task_type', 'document_upload')->whereNotIn('status', ['completed', 'skipped'])->isNotEmpty()) {
            return 'waiting_for_employee';
        }

        if ($case->tasks->where('task_type', 'payroll_setup')->whereNotIn('status', ['completed', 'skipped'])->isNotEmpty()) {
            return 'waiting_for_payroll';
        }

        return 'in_progress';
    }
}
