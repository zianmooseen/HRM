<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\LegalRuleSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_filter_company_scoped_audit_logs(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class]);

        [$company, $user] = $this->userWithRole('company_admin');
        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => 'AUD-001',
            'first_name' => 'Audit',
            'last_name' => 'Worker',
            'display_name' => 'Audit Worker',
            'status' => 'active',
        ]);
        $this->audit($company, $user, 'employee.updated', Employee::class, $employee->id, ['id' => $employee->id], ['id' => $employee->id, 'status' => 'active']);
        $this->audit($company, $user, 'leave_request.approved', Employee::class, $employee->id, ['employee_id' => $employee->id], ['employee_id' => $employee->id]);
        $otherCompany = Company::query()->create(['name' => 'Other Company']);
        $this->audit($otherCompany, $user, 'employee.updated', Employee::class, 999);

        Sanctum::actingAs($user);

        $this->getJson('/api/audit-logs?module=employee&employee_id='.$employee->id)
            ->assertOk()
            ->assertJsonPath('data.audit_logs.0.action', 'employee.updated')
            ->assertJsonPath('data.audit_logs.0.auditable_id', $employee->id)
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_audit_log_detail_shows_authorized_snapshots(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class]);

        [$company, $user] = $this->userWithRole('company_admin');
        $log = $this->audit($company, $user, 'employee.updated', Employee::class, 10, ['status' => 'draft'], ['status' => 'active']);

        Sanctum::actingAs($user);

        $this->getJson("/api/audit-logs/{$log->id}")
            ->assertOk()
            ->assertJsonPath('data.audit_log.snapshots_visible', true)
            ->assertJsonPath('data.audit_log.before.status', 'draft')
            ->assertJsonPath('data.audit_log.after.status', 'active');
    }

    public function test_sensitive_snapshots_are_redacted_without_matching_permission(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class]);

        [$company, $user] = $this->userWithRole('hr_manager');
        $log = $this->audit($company, $user, 'employee_salary_component.updated', Employee::class, 10, ['amount' => 1000], ['amount' => 1200]);

        Sanctum::actingAs($user);

        $this->getJson("/api/audit-logs/{$log->id}")
            ->assertOk()
            ->assertJsonPath('data.audit_log.snapshots_visible', false)
            ->assertJsonPath('data.audit_log.before', null)
            ->assertJsonPath('data.audit_log.after', null);
    }

    public function test_employee_without_audit_permission_cannot_view_audit_logs(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class]);

        [, $user] = $this->userWithRole('employee');

        Sanctum::actingAs($user);

        $this->getJson('/api/audit-logs')->assertForbidden();
    }

    private function userWithRole(string $roleSlug): array
    {
        $company = Company::query()->create(['name' => 'Demo Company']);
        $user = User::factory()->create();
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

        $user->roles()->attach($role->id, [
            'company_id' => $company->id,
            'scope' => $roleSlug === 'employee' ? 'self' : 'company',
        ]);

        return [$company, $user];
    }

    private function audit(Company $company, User $user, string $action, string $auditableType, ?int $auditableId = null, ?array $before = null, ?array $after = null): AuditLog
    {
        return AuditLog::query()->create([
            'company_id' => $company->id,
            'actor_user_id' => $user->id,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'before_json' => $before,
            'after_json' => $after,
        ]);
    }
}
