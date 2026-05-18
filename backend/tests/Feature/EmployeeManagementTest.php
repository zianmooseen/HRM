<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
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
            'last_name' => 'Khan',
            'work_email' => 'aisha@example.com',
            'status' => 'draft',
            'basic_salary' => 12000,
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.employee.employee_code', 'EMP-001');

        $this->getJson('/api/employees')
            ->assertOk()
            ->assertJsonCount(1, 'data.employees')
            ->assertJsonPath('data.employees.0.employee_code', 'EMP-001');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'employee.created',
            'company_id' => $company->id,
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
}
