<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\LeaveRequest;
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
            ->assertJsonPath('data.audit_log.employees', [])
            ->assertJsonPath('data.audit_log.reference_labels', [])
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

    public function test_audit_details_include_employee_name_and_current_branch_without_cross_company_enrichment(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class]);
        [$company, $user] = $this->userWithRole('company_admin');
        $branch = $company->branches()->create(['name' => 'Dubai', 'code' => 'DXB']);
        $employee = Employee::create([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'employee_code' => 'AUD-009',
            'first_name' => 'Alex', 'last_name' => 'Morgan', 'display_name' => 'Alex Morgan', 'status' => 'active',
        ]);
        $foreignCompany = Company::create(['name' => 'Other Company']);
        $foreign = Employee::create([
            'company_id' => $foreignCompany->id, 'employee_code' => 'FOREIGN',
            'first_name' => 'Private', 'last_name' => 'Employee', 'display_name' => 'Private Employee', 'status' => 'active',
        ]);
        $log = $this->audit($company, $user, 'leave_request.updated', LeaveRequest::class, 41,
            ['employee_id' => $foreign->id], ['employee_id' => (string) $employee->id]);
        $direct = $this->audit($company, $user, 'employee.deleted', Employee::class, $employee->id);
        $employee->delete();
        $branch->delete();
        Sanctum::actingAs($user);

        foreach ([$log, $direct] as $event) {
            $this->getJson('/api/audit-logs/'.$event->id)->assertOk()
                ->assertJsonCount(1, 'data.audit_log.employees')
                ->assertJsonPath('data.audit_log.employees.0.name', 'Alex Morgan')
                ->assertJsonPath('data.audit_log.employees.0.employee_code', 'AUD-009')
                ->assertJsonPath('data.audit_log.employees.0.branch_name', 'Dubai')
                ->assertJsonMissing(['name' => 'Private Employee']);
        }
        // Listing events must not enrich redacted snapshots or add per-row employee lookups.
        $this->getJson('/api/audit-logs')->assertOk()->assertJsonPath('data.audit_logs.0.employees', []);
    }

    public function test_audit_details_handle_missing_employees_and_malformed_snapshot_ids(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class]);
        [$company, $user] = $this->userWithRole('company_admin');
        Sanctum::actingAs($user);
        foreach ([null, 'invalid', ['nested' => 1], 999999, '999999999999999999999999999999'] as $id) {
            $log = $this->audit($company, $user, 'leave_request.created', LeaveRequest::class, 41,
                null, ['employee_id' => $id]);
            $this->getJson('/api/audit-logs/'.$log->id)->assertOk()->assertJsonPath('data.audit_log.employees', []);
        }
    }

    public function test_audit_reference_names_resolve_old_new_nested_and_list_ids_with_tenant_isolation(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class]);
        [$company, $user] = $this->userWithRole('company_admin');
        $oldRole = Role::where('slug', 'employee')->firstOrFail();
        $newRole = Role::where('slug', 'hr_manager')->firstOrFail();
        $branch = $company->branches()->create(['name' => 'Dubai', 'code' => 'DXB']);
        $department = Department::create(['company_id' => $company->id, 'name' => 'Finance', 'code' => 'FIN']);
        $job = JobTitle::create(['company_id' => $company->id, 'title' => 'Accountant', 'code' => 'ACC']);
        $foreignCompany = Company::create(['name' => 'Private Company']);
        $foreignRole = Role::create(['company_id' => $foreignCompany->id, 'name' => 'Private Role', 'slug' => 'private_role']);
        $foreignUser = User::factory()->create();
        $foreignUser->roles()->attach($oldRole->id, ['company_id' => $foreignCompany->id, 'scope' => 'company']);
        $log = $this->audit($company, $user, 'employee.updated', Employee::class, 999,
            ['role_id' => $oldRole->id],
            ['role_id' => $newRole->id, 'role_ids' => [$newRole->id, $foreignRole->id],
                'assignment' => ['department_id' => $department->id, 'job_title_id' => $job->id],
                'branch_id' => $branch->id, 'updated_by' => $foreignUser->id, 'total_days' => 7]);
        $department->delete();
        Sanctum::actingAs($user);
        $response = $this->getJson('/api/audit-logs/'.$log->id)->assertOk();
        $labels = $response->json('data.audit_log.reference_labels');
        $this->assertSame($oldRole->name, $labels['role_id'][$oldRole->id]);
        $this->assertSame($newRole->name, $labels['role_id'][$newRole->id]);
        $this->assertSame('Finance', $labels['department_id'][$department->id]);
        $this->assertSame('Accountant', $labels['job_title_id'][$job->id]);
        $this->assertSame('Dubai', $labels['branch_id'][$branch->id]);
        $this->assertSame($user->name, $labels['actor_user_id'][$user->id]);
        $this->assertArrayNotHasKey($foreignRole->id, $labels['role_ids']);
        $this->assertSame([], $labels['updated_by']);
        $this->assertArrayNotHasKey('total_days', $labels);
        $response->assertJsonPath('data.audit_log.after.role_id', $newRole->id);
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
