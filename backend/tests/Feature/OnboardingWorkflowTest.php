<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeOnboardingCase;
use App\Models\EmployeeOnboardingTask;
use App\Models\OnboardingTemplate;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OnboardingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_create_template_start_tasks_and_complete_onboarding(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        [$company, $user] = $this->companyAdmin();
        $employee = $this->employee($company);
        Sanctum::actingAs($user);

        $templateResponse = $this->postJson('/api/onboarding-templates', [
            'name' => 'Standard hire',
            'is_default' => true,
            'tasks' => [
                [
                    'title' => 'Upload passport',
                    'task_type' => 'document_upload',
                    'assigned_to_role' => 'hr_manager',
                    'required' => true,
                    'due_days_after_start' => 2,
                ],
                [
                    'title' => 'Set payroll details',
                    'task_type' => 'payroll_setup',
                    'assigned_to_role' => 'payroll_manager',
                    'required' => true,
                    'due_days_after_start' => 5,
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.onboarding_template.name', 'Standard hire')
            ->assertJsonCount(2, 'data.onboarding_template.tasks');

        $templateId = $templateResponse->json('data.onboarding_template.id');

        $startResponse = $this->postJson("/api/employees/{$employee->id}/onboarding/start", [
            'onboarding_template_id' => $templateId,
        ])
            ->assertCreated()
            ->assertJsonPath('data.onboarding_case.status', 'in_progress')
            ->assertJsonPath('data.onboarding_case.progress.total_tasks', 2);

        $employee->refresh();
        $this->assertSame('onboarding', $employee->status);

        $caseId = $startResponse->json('data.onboarding_case.id');
        $tasks = EmployeeOnboardingTask::query()->where('employee_onboarding_case_id', $caseId)->orderBy('id')->get();

        $this->postJson("/api/onboarding-tasks/{$tasks[0]->id}", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.onboarding_case.progress.completed_tasks', 1);

        $this->postJson("/api/onboarding-cases/{$caseId}/complete")
            ->assertUnprocessable()
            ->assertJsonPath('errors.tasks.0', 'Complete or skip all required onboarding tasks before activation.');

        $this->postJson("/api/onboarding-tasks/{$tasks[1]->id}", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.onboarding_case.progress.completed_tasks', 2);

        $this->postJson("/api/onboarding-cases/{$caseId}/complete")
            ->assertOk()
            ->assertJsonPath('data.onboarding_case.status', 'completed');

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'status' => 'active']);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'onboarding_case.completed',
        ]);
    }

    public function test_onboarding_cannot_start_for_foreign_company_employee(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        [$company, $user] = $this->companyAdmin();
        $foreignCompany = Company::query()->create(['name' => 'Foreign']);
        $foreignEmployee = $this->employee($foreignCompany);
        $template = OnboardingTemplate::query()->create([
            'company_id' => $company->id,
            'name' => 'Standard hire',
            'status' => 'active',
        ]);
        $template->tasks()->create([
            'title' => 'HR review',
            'task_type' => 'hr_review',
            'required' => true,
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/employees/{$foreignEmployee->id}/onboarding/start", [
            'onboarding_template_id' => $template->id,
        ])->assertForbidden();

        $this->assertDatabaseCount('employee_onboarding_cases', 0);
    }

    private function companyAdmin(): array
    {
        $company = Company::query()->create(['name' => 'Demo Company']);
        $user = User::factory()->create();
        $role = Role::query()->where('slug', 'company_admin')->firstOrFail();

        $user->roles()->attach($role->id, [
            'company_id' => $company->id,
            'scope' => 'company',
        ]);

        return [$company, $user];
    }

    private function employee(Company $company): Employee
    {
        return Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => 'ONB-'.$company->id,
            'first_name' => 'Noura',
            'last_name' => 'Ali',
            'display_name' => 'Noura Ali',
            'status' => 'draft',
            'hire_date' => '2026-06-01',
        ]);
    }
}
