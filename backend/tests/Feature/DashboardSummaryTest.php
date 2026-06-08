<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Document;
use App\Models\Employee;
use App\Models\EmployeeOnboardingCase;
use App\Models\EmiratisationSnapshot;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\LegalRuleSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_view_operational_dashboard_summary(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class]);

        [$company, $user] = $this->companyAdmin();
        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => 'DASH-001',
            'first_name' => 'Dashboard',
            'last_name' => 'Worker',
            'display_name' => 'Dashboard Worker',
            'status' => 'active',
            'contract_end_date' => now()->addDays(20)->toDateString(),
        ]);
        Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => 'DASH-002',
            'first_name' => 'Onboarding',
            'last_name' => 'Worker',
            'display_name' => 'Onboarding Worker',
            'status' => 'onboarding',
        ]);
        Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => 'DASH-003',
            'first_name' => 'Terminated',
            'last_name' => 'Worker',
            'display_name' => 'Terminated Worker',
            'status' => 'terminated',
        ]);
        EmployeeOnboardingCase::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'status' => 'in_progress',
        ]);
        AttendanceRecord::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'source' => 'manual',
        ]);
        $leaveType = LeaveType::query()->create([
            'company_id' => $company->id,
            'code' => 'annual_leave',
            'name' => 'Annual Leave',
            'category' => 'annual',
            'paid_type' => 'paid',
            'status' => 'active',
        ]);
        LeaveRequest::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'total_days' => 1,
            'working_days' => 1,
            'status' => 'pending',
            'requested_by' => $user->id,
        ]);
        PayrollPeriod::query()->create([
            'company_id' => $company->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'pay_date' => now()->endOfMonth()->toDateString(),
            'status' => 'processed',
            'created_by' => $user->id,
        ]);
        Document::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'document_type' => 'passport',
            'title' => 'Passport',
            'original_file_name' => 'passport.pdf',
            'disk' => 'local',
            'path' => 'documents/passport.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'expiry_date' => now()->addDays(15)->toDateString(),
            'status' => 'active',
        ]);
        EmiratisationSnapshot::query()->create([
            'company_id' => $company->id,
            'snapshot_date' => now()->toDateString(),
            'total_active_workers' => 50,
            'total_skilled_workers' => 50,
            'total_active_uae_citizens' => 0,
            'total_skilled_uae_citizens' => 0,
            'required_uae_citizens' => 1,
            'missing_uae_citizens' => 1,
            'estimated_contribution_amount' => 96000,
            'compliance_status' => 'non_compliant',
        ]);
        AuditLog::query()->create([
            'company_id' => $company->id,
            'actor_user_id' => $user->id,
            'action' => 'employee.created',
            'auditable_type' => Employee::class,
            'auditable_id' => $employee->id,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.dashboard.employee_counts.active', 1)
            ->assertJsonPath('data.dashboard.employee_counts.onboarding', 1)
            ->assertJsonPath('data.dashboard.employee_counts.terminated', 1)
            ->assertJsonPath('data.dashboard.attendance_today.present', 1)
            ->assertJsonPath('data.dashboard.leave.pending_requests', 1)
            ->assertJsonPath('data.dashboard.payroll.latest_period.status', 'processed')
            ->assertJsonPath('data.dashboard.compliance.latest_emiratisation_snapshot.compliance_status', 'non_compliant')
            ->assertJsonPath('data.dashboard.alerts.contracts_expiring.0.display_name', 'Dashboard Worker')
            ->assertJsonPath('data.dashboard.alerts.documents_expiring.0.title', 'Passport')
            ->assertJsonPath('data.dashboard.recent_audit_logs.0.action', 'employee.created');
    }

    public function test_dashboard_requires_employee_view_permission(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class]);

        $user = User::factory()->create();
        $company = Company::query()->create(['name' => 'Demo Company']);
        $role = Role::query()->where('slug', 'employee')->firstOrFail();
        $user->roles()->attach($role->id, ['company_id' => $company->id, 'scope' => 'self']);

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard')->assertForbidden();
    }

    public function test_company_admin_can_filter_dashboard_metrics_by_branch(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class]);

        [$company, $user] = $this->companyAdmin();
        $dubai = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Dubai',
            'code' => 'DXB',
            'status' => 'active',
        ]);
        $abuDhabi = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Abu Dhabi',
            'code' => 'AUH',
            'status' => 'active',
        ]);
        $dubaiEmployee = Employee::query()->create([
            'company_id' => $company->id,
            'branch_id' => $dubai->id,
            'employee_code' => 'DXB-001',
            'first_name' => 'Dubai',
            'last_name' => 'Worker',
            'display_name' => 'Dubai Worker',
            'status' => 'active',
        ]);
        Employee::query()->create([
            'company_id' => $company->id,
            'branch_id' => $abuDhabi->id,
            'employee_code' => 'AUH-001',
            'first_name' => 'Abu Dhabi',
            'last_name' => 'Worker',
            'display_name' => 'Abu Dhabi Worker',
            'status' => 'active',
        ]);
        AttendanceRecord::query()->create([
            'company_id' => $company->id,
            'employee_id' => $dubaiEmployee->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'source' => 'manual',
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/dashboard?branch_id={$dubai->id}")
            ->assertOk()
            ->assertJsonPath('data.dashboard.scope.level', 'branch')
            ->assertJsonPath('data.dashboard.scope.branch.id', $dubai->id)
            ->assertJsonPath('data.dashboard.employee_counts.active', 1)
            ->assertJsonPath('data.dashboard.attendance_today.present', 1);
    }

    public function test_company_admin_cannot_select_another_company_dashboard(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class]);

        [, $user] = $this->companyAdmin();
        $otherCompany = Company::query()->create(['name' => 'Other Company']);

        Sanctum::actingAs($user);

        $this->getJson("/api/dashboard?company_id={$otherCompany->id}")->assertForbidden();
    }

    public function test_super_admin_can_select_any_company_dashboard(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class]);

        Company::query()->create(['name' => 'First Company']);
        $selectedCompany = Company::query()->create(['name' => 'Selected Company']);
        Employee::query()->create([
            'company_id' => $selectedCompany->id,
            'employee_code' => 'SYS-001',
            'first_name' => 'System',
            'last_name' => 'View',
            'display_name' => 'System View',
            'status' => 'active',
        ]);
        $user = User::factory()->create();
        $role = Role::query()->where('slug', 'super_admin')->firstOrFail();
        $user->roles()->attach($role->id, ['scope' => 'global']);

        Sanctum::actingAs($user);

        $this->getJson("/api/dashboard?company_id={$selectedCompany->id}")
            ->assertOk()
            ->assertJsonPath('data.dashboard.scope.can_select_company', true)
            ->assertJsonPath('data.dashboard.scope.company.id', $selectedCompany->id)
            ->assertJsonPath('data.dashboard.employee_counts.active', 1);
    }

    private function companyAdmin(): array
    {
        $company = Company::query()->create(['name' => 'Demo Company', 'default_currency' => 'AED']);
        $user = User::factory()->create();
        $role = Role::query()->where('slug', 'company_admin')->firstOrFail();

        $user->roles()->attach($role->id, [
            'company_id' => $company->id,
            'scope' => 'company',
        ]);

        return [$company, $user];
    }
}
