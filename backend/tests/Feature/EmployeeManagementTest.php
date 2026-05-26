<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeServicePeriod;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_manage_employees_only_inside_assigned_company(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $company = Company::query()->create(['name' => 'Demo Company']);
        $otherCompany = Company::query()->create(['name' => 'Other Company']);
        $user = User::factory()->create();
        $role = Role::query()->where('slug', 'company_admin')->firstOrFail();

        $user->roles()->attach($role->id, [
            'company_id' => $company->id,
            'scope' => 'company',
        ]);

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Dubai HQ',
            'code' => 'DXB',
            'status' => 'active',
        ]);

        Employee::query()->create([
            'company_id' => $otherCompany->id,
            'employee_code' => 'OTHER-001',
            'first_name' => 'Other',
            'last_name' => 'Employee',
            'display_name' => 'Other Employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/employees', [
            'branch_id' => $branch->id,
            'employee_code' => 'EMP-001',
            'first_name' => 'Aisha',
            'middle_name' => 'M',
            'last_name' => 'Khan',
            'personal_email' => 'aisha.personal@example.com',
            'work_email' => 'aisha@example.com',
            'phone' => '+971500000001',
            'gender' => 'female',
            'nationality' => 'United Arab Emirates',
            'is_uae_citizen' => true,
            'skill_level' => 'level_1',
            'is_skilled_worker' => true,
            'work_permit_type' => 'citizen',
            'date_of_birth' => '1990-02-10',
            'hire_date' => '2026-01-01',
            'contract_start_date' => '2026-01-01',
            'contract_end_date' => '2026-12-31',
            'status' => 'draft',
            'basic_salary' => 12000,
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.employee.employee_code', 'EMP-001')
            ->assertJsonPath('data.employee.is_uae_citizen', true)
            ->assertJsonPath('data.employee.date_of_birth', '1990-02-10');

        $this->getJson('/api/employees')
            ->assertOk()
            ->assertJsonCount(1, 'data.employees')
            ->assertJsonPath('data.employees.0.employee_code', 'EMP-001');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'employee.created',
            'company_id' => $company->id,
        ]);
        $this->assertDatabaseHas('employee_service_periods', [
            'company_id' => $company->id,
            'start_date' => '2026-01-01 00:00:00',
            'end_date' => '2026-12-31 00:00:00',
            'status' => 'active',
        ]);
    }

    public function test_employee_create_rejects_foreign_company_branch(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $company = Company::query()->create(['name' => 'Demo Company']);
        $otherCompany = Company::query()->create(['name' => 'Other Company']);
        $user = User::factory()->create();
        $role = Role::query()->where('slug', 'company_admin')->firstOrFail();

        $user->roles()->attach($role->id, [
            'company_id' => $company->id,
            'scope' => 'company',
        ]);

        $foreignBranch = Branch::query()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Other HQ',
            'code' => 'OTH',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/employees', [
            'branch_id' => $foreignBranch->id,
            'employee_code' => 'EMP-002',
            'first_name' => 'Sara',
            'last_name' => 'Ali',
            'status' => 'draft',
        ])->assertUnprocessable();
    }

    public function test_company_admin_can_extend_employee_contract(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        [$company, $user] = $this->companyAdmin();
        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-EXT',
            'first_name' => 'Extend',
            'last_name' => 'Employee',
            'display_name' => 'Extend Employee',
            'status' => 'active',
            'hire_date' => '2026-01-01',
            'contract_start_date' => '2026-01-01',
            'contract_end_date' => '2026-12-31',
        ]);
        EmployeeServicePeriod::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/employees/{$employee->id}/service-periods/extend", [
            'end_date' => '2027-12-31',
            'change_reason' => 'Renewed fixed-term contract.',
        ])->assertOk()
            ->assertJsonPath('data.service_period.end_date', '2027-12-31');

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'contract_end_date' => '2027-12-31 00:00:00',
        ]);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'employee_contract.extended']);
    }

    public function test_company_admin_can_rehire_terminated_employee(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        [$company, $user] = $this->companyAdmin();
        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-REHIRE',
            'first_name' => 'Rehire',
            'last_name' => 'Employee',
            'display_name' => 'Rehire Employee',
            'status' => 'terminated',
            'hire_date' => '2024-01-01',
            'contract_start_date' => '2024-01-01',
            'contract_end_date' => '2025-01-01',
            'basic_salary' => 8000,
        ]);
        EmployeeServicePeriod::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'start_date' => '2024-01-01',
            'end_date' => '2025-01-01',
            'status' => 'terminated',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/employees/{$employee->id}/service-periods/rehire", [
            'start_date' => '2026-02-01',
            'end_date' => '2027-02-01',
            'contract_type' => 'fixed_term',
            'employment_type' => 'full_time',
            'basic_salary' => 10000,
            'change_reason' => 'Rehired for new role.',
        ])->assertCreated()
            ->assertJsonPath('data.employee.status', 'active')
            ->assertJsonPath('data.service_period.start_date', '2026-02-01');

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'status' => 'active',
            'hire_date' => '2026-02-01 00:00:00',
            'basic_salary' => 10000,
        ]);
        $this->assertDatabaseHas('employee_service_periods', [
            'employee_id' => $employee->id,
            'start_date' => '2026-02-01 00:00:00',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'employee.rehired']);
    }

    public function test_company_admin_can_filter_contracts_expiring_soon(): void
    {
        Carbon::setTestNow('2026-05-26 09:00:00');
        $this->seed(RoleAndPermissionSeeder::class);

        [$company, $user] = $this->companyAdmin();
        Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => 'EXP-SOON',
            'first_name' => 'Soon',
            'last_name' => 'Expiry',
            'display_name' => 'Soon Expiry',
            'status' => 'active',
            'contract_end_date' => '2026-06-20',
        ]);
        Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => 'EXP-LATER',
            'first_name' => 'Later',
            'last_name' => 'Expiry',
            'display_name' => 'Later Expiry',
            'status' => 'active',
            'contract_end_date' => '2026-09-20',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/employees?contract_expiring_days=60')
            ->assertOk()
            ->assertJsonCount(1, 'data.employees')
            ->assertJsonPath('data.employees.0.employee_code', 'EXP-SOON')
            ->assertJsonPath('data.employees.0.contract_expiry_status', 'critical');

        Carbon::setTestNow();
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
}
