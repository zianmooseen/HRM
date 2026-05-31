<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyComplianceSetting;
use App\Models\Employee;
use App\Models\LegalRuleSet;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\LegalRuleSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SickLeavePayCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_sick_leave_stores_pay_tier_items(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class, LeaveTypeSeeder::class]);

        [$company, $user] = $this->companyAdmin();
        $employee = $this->employee($company);
        $sickLeave = LeaveType::query()->where('code', 'sick_leave')->firstOrFail();

        LeaveRequest::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $sickLeave->id,
            'start_date' => '2026-01-06',
            'end_date' => '2026-01-19',
            'total_days' => 14,
            'working_days' => 10,
            'status' => 'approved',
            'requested_by' => $user->id,
            'approved_by' => $user->id,
            'approved_at' => now(),
            'medical_certificate_document_id' => 11,
        ]);

        $request = LeaveRequest::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $sickLeave->id,
            'start_date' => '2026-02-02',
            'end_date' => '2026-02-27',
            'total_days' => 26,
            'working_days' => 20,
            'status' => 'pending',
            'requested_by' => $user->id,
            'medical_certificate_document_id' => 22,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/leave-requests/{$request->id}/sick-pay")
            ->assertOk()
            ->assertJsonPath('data.calculation.previously_used_days', 10)
            ->assertJsonPath('data.calculation.items.0.pay_tier', 'full_pay')
            ->assertJsonPath('data.calculation.items.0.days', 5)
            ->assertJsonPath('data.calculation.items.1.pay_tier', 'half_pay')
            ->assertJsonPath('data.calculation.items.1.days', 15);

        $this->postJson("/api/leave-requests/{$request->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.leave_request.status', 'approved')
            ->assertJsonCount(2, 'data.leave_request.pay_calculation_items');

        $this->assertDatabaseHas('leave_pay_calculation_items', [
            'leave_request_id' => $request->id,
            'pay_tier' => 'full_pay',
            'days' => 5,
            'pay_percentage' => 100,
            'daily_wage' => 300,
            'deduction_amount' => 0,
        ]);
        $this->assertDatabaseHas('leave_pay_calculation_items', [
            'leave_request_id' => $request->id,
            'pay_tier' => 'half_pay',
            'days' => 15,
            'pay_percentage' => 50,
            'daily_wage' => 300,
            'deduction_amount' => 2250,
        ]);
    }

    public function test_sick_leave_approval_requires_medical_document(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class, LeaveTypeSeeder::class]);

        [$company, $user] = $this->companyAdmin();
        $employee = $this->employee($company);
        $sickLeave = LeaveType::query()->where('code', 'sick_leave')->firstOrFail();
        $request = LeaveRequest::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $sickLeave->id,
            'start_date' => '2026-02-02',
            'end_date' => '2026-02-06',
            'total_days' => 5,
            'working_days' => 5,
            'status' => 'pending',
            'requested_by' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/leave-requests/{$request->id}/sick-pay")
            ->assertOk()
            ->assertJsonPath('data.calculation.eligible', false)
            ->assertJsonPath('data.calculation.reason', 'medical_document_required');

        $this->postJson("/api/leave-requests/{$request->id}/approve")
            ->assertUnprocessable()
            ->assertJsonPath('errors.medical_certificate_document_id.0', 'Medical document is required before approving sick leave.');
    }

    public function test_sick_leave_during_probation_is_unpaid_by_default(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class, LeaveTypeSeeder::class]);

        [$company, $user] = $this->companyAdmin();
        $employee = $this->employee($company, ['probation_end_date' => '2026-12-31']);
        $sickLeave = LeaveType::query()->where('code', 'sick_leave')->firstOrFail();
        $request = LeaveRequest::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $sickLeave->id,
            'start_date' => '2026-02-02',
            'end_date' => '2026-02-06',
            'total_days' => 5,
            'working_days' => 5,
            'status' => 'pending',
            'requested_by' => $user->id,
            'medical_certificate_document_id' => 33,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/leave-requests/{$request->id}/sick-pay")
            ->assertOk()
            ->assertJsonPath('data.calculation.items.0.pay_tier', 'unpaid')
            ->assertJsonPath('data.calculation.items.0.days', 5)
            ->assertJsonPath('data.calculation.items.0.deduction_amount', 1500);
    }

    public function test_sick_leave_pay_uses_company_payroll_day_divisor_policy(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class, LeaveTypeSeeder::class]);

        [$company, $user] = $this->companyAdmin();
        $employee = $this->employee($company);
        $ruleSet = LegalRuleSet::query()->firstOrFail();
        $sickLeave = LeaveType::query()->where('code', 'sick_leave')->firstOrFail();
        CompanyComplianceSetting::query()->create([
            'company_id' => $company->id,
            'legal_rule_set_id' => $ruleSet->id,
            'payroll_day_divisor' => 'actual_calendar_days',
            'annual_leave_accrual_method' => 'monthly',
            'annual_leave_carry_forward_allowed' => true,
            'public_holidays_count_as_annual_leave' => false,
            'sick_leave_requires_medical_certificate' => true,
            'sick_leave_notification_days' => 3,
            'emiratisation_monitoring_enabled' => false,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $request = LeaveRequest::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $sickLeave->id,
            'start_date' => '2026-02-02',
            'end_date' => '2026-02-06',
            'total_days' => 5,
            'working_days' => 5,
            'status' => 'pending',
            'requested_by' => $user->id,
            'medical_certificate_document_id' => 44,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/leave-requests/{$request->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.leave_request.pay_calculation_items.0.daily_wage', '321.43')
            ->assertJsonPath('data.leave_request.pay_calculation_items.0.calculation_basis', 'basic_salary_actual_calendar_days');
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

    private function employee(Company $company, array $overrides = []): Employee
    {
        return Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => 'SICK-'.$company->id,
            'first_name' => 'Aisha',
            'last_name' => 'Khan',
            'display_name' => 'Aisha Khan',
            'status' => 'active',
            'hire_date' => '2025-01-01',
            'basic_salary' => 9000,
            ...$overrides,
        ]);
    }
}
