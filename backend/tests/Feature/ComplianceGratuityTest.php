<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeServicePeriod;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\LegalRuleSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ComplianceGratuityTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_calculate_uae_gratuity_for_employee(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class]);

        [$company, $user] = $this->companyAdmin();
        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => 'GRAT-001',
            'first_name' => 'Omar',
            'last_name' => 'Said',
            'display_name' => 'Omar Said',
            'status' => 'active',
            'hire_date' => '2020-01-01',
            'basic_salary' => 9000,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/compliance/gratuity', [
            'employee_id' => $employee->id,
            'termination_date' => '2026-01-01',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.gratuity.daily_wage', 300)
            ->assertJsonPath('data.gratuity.gratuity_days', 135.25)
            ->assertJsonPath('data.gratuity.gratuity_amount', 40573.97);
    }

    public function test_gratuity_is_zero_before_one_year_of_service(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class]);

        [$company, $user] = $this->companyAdmin();
        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => 'GRAT-002',
            'first_name' => 'Mona',
            'last_name' => 'Ali',
            'display_name' => 'Mona Ali',
            'status' => 'active',
            'hire_date' => '2026-01-01',
            'basic_salary' => 9000,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/compliance/gratuity', [
            'employee_id' => $employee->id,
            'termination_date' => '2026-06-01',
        ])->assertOk()
            ->assertJsonPath('data.gratuity.gratuity_days', 0)
            ->assertJsonPath('data.gratuity.gratuity_amount', 0);
    }

    public function test_gratuity_rejects_foreign_company_employee(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class]);

        [, $user] = $this->companyAdmin();
        $otherCompany = Company::query()->create(['name' => 'Other Company']);
        $foreignEmployee = Employee::query()->create([
            'company_id' => $otherCompany->id,
            'employee_code' => 'GRAT-003',
            'first_name' => 'Foreign',
            'last_name' => 'Employee',
            'display_name' => 'Foreign Employee',
            'status' => 'active',
            'hire_date' => '2020-01-01',
            'basic_salary' => 9000,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/compliance/gratuity', [
            'employee_id' => $foreignEmployee->id,
            'termination_date' => '2026-01-01',
        ])->assertUnprocessable();
    }

    public function test_company_admin_can_create_termination_and_mark_final_settlement_paid(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class]);

        [$company, $user] = $this->companyAdmin();
        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => 'TERM-001',
            'first_name' => 'Layla',
            'last_name' => 'Saleh',
            'display_name' => 'Layla Saleh',
            'status' => 'active',
            'hire_date' => '2020-01-01',
            'basic_salary' => 9000,
        ]);

        Sanctum::actingAs($user);

        $terminationId = $this->postJson("/api/employees/{$employee->id}/termination", [
            'termination_date' => '2026-01-01',
            'last_working_date' => '2025-12-31',
            'termination_type' => 'employee_resignation',
            'termination_reason' => 'Resigned.',
            'leave_encashment_amount' => 1200,
            'notice_paid_amount' => 0,
            'other_earnings_amount' => 500,
            'deductions_amount' => 300,
        ])->assertCreated()
            ->assertJsonPath('data.employee_termination.status', 'draft')
            ->assertJsonPath('data.employee_termination.gratuity_amount', '40573.97')
            ->assertJsonPath('data.employee_termination.final_settlement_amount', '41973.97')
            ->json('data.employee_termination.id');

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'status' => 'terminated',
            'contract_end_date' => '2026-01-01 00:00:00',
        ]);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'employee.terminated']);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'employee_termination.created']);

        $this->postJson("/api/employee-terminations/{$terminationId}/mark-paid", [
            'paid_amount' => 41973.97,
            'paid_at' => '2026-01-05',
            'payment_reference' => 'BANK-REF-1',
        ])->assertOk()
            ->assertJsonPath('data.employee_termination.status', 'paid')
            ->assertJsonPath('data.employee_termination.payment_reference', 'BANK-REF-1');

        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'employee_termination.paid']);
    }

    public function test_termination_rejects_foreign_company_employee(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class]);

        [, $user] = $this->companyAdmin();
        $otherCompany = Company::query()->create(['name' => 'Other Company']);
        $foreignEmployee = Employee::query()->create([
            'company_id' => $otherCompany->id,
            'employee_code' => 'TERM-002',
            'first_name' => 'Foreign',
            'last_name' => 'Employee',
            'display_name' => 'Foreign Employee',
            'status' => 'active',
            'hire_date' => '2020-01-01',
            'basic_salary' => 9000,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/employees/{$foreignEmployee->id}/termination", [
            'termination_date' => '2026-01-01',
            'termination_type' => 'company_initiated',
        ])->assertForbidden();
    }

    public function test_gratuity_uses_active_service_period_for_rehired_employee(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class]);

        [$company, $user] = $this->companyAdmin();
        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => 'GRAT-REHIRE',
            'first_name' => 'Rehire',
            'last_name' => 'Worker',
            'display_name' => 'Rehire Worker',
            'status' => 'active',
            'hire_date' => '2020-01-01',
            'basic_salary' => 9000,
        ]);
        EmployeeServicePeriod::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'start_date' => '2020-01-01',
            'end_date' => '2023-01-01',
            'status' => 'terminated',
        ]);
        EmployeeServicePeriod::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'start_date' => '2026-01-01',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/compliance/gratuity', [
            'employee_id' => $employee->id,
            'termination_date' => '2026-06-01',
        ])->assertOk()
            ->assertJsonPath('data.gratuity.gratuity_days', 0)
            ->assertJsonPath('data.gratuity.gratuity_amount', 0);
    }

    public function test_company_admin_can_view_and_update_compliance_settings(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class]);

        [$company, $user] = $this->companyAdmin();

        Sanctum::actingAs($user);

        $this->getJson('/api/compliance/settings')
            ->assertOk()
            ->assertJsonPath('data.compliance_settings.company_id', $company->id)
            ->assertJsonPath('data.compliance_settings.payroll_day_divisor', 'calendar_30')
            ->assertJsonPath('data.compliance_settings.sick_leave_notification_days', 3);

        $this->putJson('/api/compliance/settings', [
            'payroll_day_divisor' => 'actual_calendar_days',
            'annual_leave_accrual_method' => 'monthly',
            'annual_leave_carry_forward_allowed' => true,
            'annual_leave_max_carry_forward_days' => 15,
            'public_holidays_count_as_annual_leave' => false,
            'sick_leave_requires_medical_certificate' => true,
            'sick_leave_notification_days' => 2,
            'emiratisation_monitoring_enabled' => true,
        ])->assertOk()
            ->assertJsonPath('data.compliance_settings.payroll_day_divisor', 'actual_calendar_days')
            ->assertJsonPath('data.compliance_settings.annual_leave_max_carry_forward_days', '15.00')
            ->assertJsonPath('data.compliance_settings.emiratisation_monitoring_enabled', true);

        $this->assertDatabaseHas('company_compliance_settings', [
            'company_id' => $company->id,
            'payroll_day_divisor' => 'actual_calendar_days',
            'sick_leave_notification_days' => 2,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'company_compliance_settings.updated',
        ]);
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
