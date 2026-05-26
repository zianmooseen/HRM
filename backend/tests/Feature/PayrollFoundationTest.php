<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeTermination;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\SalaryComponent;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayrollFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_run_and_approve_payroll(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        [$company, $user] = $this->companyAdmin();
        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-PAY',
            'first_name' => 'Salma',
            'last_name' => 'Nasser',
            'display_name' => 'Salma Nasser',
            'status' => 'active',
            'basic_salary' => 10000,
        ]);

        Sanctum::actingAs($user);

        $allowanceId = $this->postJson('/api/salary-components', [
            'code' => 'HRA',
            'name' => 'Housing Allowance',
            'type' => 'earning',
            'is_taxable' => false,
            'is_recurring' => true,
            'status' => 'active',
        ])->assertCreated()->json('data.salary_component.id');

        $deductionId = $this->postJson('/api/salary-components', [
            'code' => 'ADV',
            'name' => 'Advance Deduction',
            'type' => 'deduction',
            'is_taxable' => false,
            'is_recurring' => true,
            'status' => 'active',
        ])->assertCreated()->json('data.salary_component.id');

        $this->postJson('/api/employee-salary-components', [
            'employee_id' => $employee->id,
            'salary_component_id' => $allowanceId,
            'amount' => 2000,
            'effective_from' => '2026-05-01',
            'status' => 'active',
        ])->assertCreated();

        $this->postJson('/api/employee-salary-components', [
            'employee_id' => $employee->id,
            'salary_component_id' => $deductionId,
            'amount' => 500,
            'effective_from' => '2026-05-01',
            'status' => 'active',
        ])->assertCreated();

        $periodId = $this->postJson('/api/payroll-periods', [
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'pay_date' => '2026-06-01',
        ])->assertCreated()->json('data.payroll_period.id');

        $this->postJson("/api/payroll-periods/{$periodId}/run")
            ->assertOk()
            ->assertJsonPath('data.payslips_created', 1)
            ->assertJsonPath('data.payroll_period.status', 'processed');

        $this->assertDatabaseHas('payslips', [
            'company_id' => $company->id,
            'payroll_period_id' => $periodId,
            'employee_id' => $employee->id,
            'gross_pay' => 12000,
            'total_deductions' => 500,
            'net_pay' => 11500,
            'status' => 'draft',
        ]);

        $this->getJson("/api/payroll-periods/{$periodId}")
            ->assertOk()
            ->assertJsonPath('data.payslips.0.net_pay', '11500.00');

        $this->postJson("/api/payroll-periods/{$periodId}/approve")
            ->assertOk()
            ->assertJsonPath('data.payroll_period.status', 'approved');

        $this->assertDatabaseHas('payslips', ['payroll_period_id' => $periodId, 'status' => 'approved']);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'payroll_period.run']);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'payroll_period.approved']);
    }

    public function test_salary_assignment_rejects_foreign_company_employee(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        [$company, $user] = $this->companyAdmin();
        $otherCompany = Company::query()->create(['name' => 'Other Company']);
        $foreignEmployee = Employee::query()->create([
            'company_id' => $otherCompany->id,
            'employee_code' => 'FOREIGN-PAY',
            'first_name' => 'Foreign',
            'last_name' => 'Employee',
            'display_name' => 'Foreign Employee',
            'status' => 'active',
        ]);
        $component = SalaryComponent::query()->create([
            'company_id' => $company->id,
            'code' => 'TRA',
            'name' => 'Transport Allowance',
            'type' => 'earning',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/employee-salary-components', [
            'employee_id' => $foreignEmployee->id,
            'salary_component_id' => $component->id,
            'amount' => 500,
            'effective_from' => '2026-05-01',
            'status' => 'active',
        ])->assertUnprocessable();
    }

    public function test_approved_payroll_period_cannot_be_rerun(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        [$company, $user] = $this->companyAdmin();
        $period = PayrollPeriod::query()->create([
            'company_id' => $company->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => 'approved',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/payroll-periods/{$period->id}/run")
            ->assertUnprocessable();
    }

    public function test_payroll_includes_final_settlement_and_marks_termination_paid_on_approval(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        [$company, $user] = $this->companyAdmin();
        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-FINAL',
            'first_name' => 'Final',
            'last_name' => 'Settlement',
            'display_name' => 'Final Settlement',
            'status' => 'terminated',
            'basic_salary' => 9000,
        ]);
        $termination = EmployeeTermination::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'termination_date' => '2026-05-20',
            'last_working_date' => '2026-05-20',
            'termination_type' => 'company_initiated',
            'basic_salary' => 9000,
            'gratuity_amount' => 7000,
            'leave_encashment_amount' => 1500,
            'notice_paid_amount' => 500,
            'other_earnings_amount' => 250,
            'deductions_amount' => 750,
            'final_settlement_amount' => 8500,
            'status' => 'draft',
            'calculation_snapshot_json' => ['source' => 'test'],
        ]);

        Sanctum::actingAs($user);

        $periodId = $this->postJson('/api/payroll-periods', [
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'pay_date' => '2026-06-01',
        ])->assertCreated()->json('data.payroll_period.id');

        $this->postJson("/api/payroll-periods/{$periodId}/run")
            ->assertOk()
            ->assertJsonPath('data.payslips_created', 1);

        $this->assertDatabaseHas('payslips', [
            'company_id' => $company->id,
            'payroll_period_id' => $periodId,
            'employee_id' => $employee->id,
            'gross_pay' => 9250,
            'total_deductions' => 750,
            'net_pay' => 8500,
        ]);
        $this->assertDatabaseHas('payslip_items', [
            'label' => 'End-of-service gratuity',
            'type' => 'earning',
            'amount' => 7000,
        ]);
        $this->assertDatabaseHas('payslip_items', [
            'label' => 'Final settlement deductions',
            'type' => 'deduction',
            'amount' => 750,
        ]);

        $this->postJson("/api/payroll-periods/{$periodId}/approve")
            ->assertOk()
            ->assertJsonPath('data.payroll_period.status', 'approved');

        $this->assertDatabaseHas('employee_terminations', [
            'id' => $termination->id,
            'status' => 'paid',
            'paid_amount' => 8500,
            'payment_reference' => 'payroll_period:'.$periodId,
        ]);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'employee_termination.paid']);
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
