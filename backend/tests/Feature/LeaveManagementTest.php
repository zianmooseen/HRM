<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\CompanyComplianceSetting;
use App\Models\LegalRuleSet;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PublicHoliday;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\LegalRuleSeeder;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaveManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_create_and_approve_leave_request(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LeaveTypeSeeder::class]);

        [$company, $user] = $this->companyAdmin();
        $employee = $this->employee($company);
        $leaveType = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

        Sanctum::actingAs($user);

        $createResponse = $this->postJson('/api/leave-requests', [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-05-20',
            'end_date' => '2026-05-22',
            'reason' => 'Family travel.',
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.leave_request.status', 'pending')
            ->assertJsonPath('data.leave_request.total_days', '3.00')
            ->assertJsonPath('data.leave_request.working_days', '3.00');

        $leaveRequestId = $createResponse->json('data.leave_request.id');

        $this->assertDatabaseHas('employee_leave_balances', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'leave_year' => 2026,
            'pending_days' => 3,
        ]);

        $this->postJson("/api/leave-requests/{$leaveRequestId}/approve")
            ->assertOk()
            ->assertJsonPath('data.leave_request.status', 'approved')
            ->assertJsonPath('data.leave_request.status_events.1.status', 'approved');

        $this->assertDatabaseHas('employee_leave_balances', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'leave_year' => 2026,
            'pending_days' => 0,
            'used_days' => 3,
        ]);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'leave_request.created']);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'leave_request.approved']);
    }

    public function test_approval_note_is_stored_with_status_history(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LeaveTypeSeeder::class]);

        [$company, $user] = $this->companyAdmin();
        $employee = $this->employee($company);
        $leaveType = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

        EmployeeLeaveBalance::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'leave_year' => 2026,
            'accrued_days' => 30,
            'closing_balance' => 30,
        ]);

        Sanctum::actingAs($user);

        $leaveRequestId = $this->postJson('/api/leave-requests', [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-05-20',
            'end_date' => '2026-05-21',
        ])->assertCreated()->json('data.leave_request.id');

        $this->postJson("/api/leave-requests/{$leaveRequestId}/approve", [
            'approval_note' => 'Coverage confirmed with branch manager.',
        ])->assertOk()
            ->assertJsonPath('data.leave_request.approval_note', 'Coverage confirmed with branch manager.')
            ->assertJsonPath('data.leave_request.status_events.1.note', 'Coverage confirmed with branch manager.');

        $this->assertDatabaseHas('leave_request_status_events', [
            'leave_request_id' => $leaveRequestId,
            'status' => 'approved',
            'note' => 'Coverage confirmed with branch manager.',
        ]);
    }

    public function test_approval_rejects_request_that_exceeds_configured_balance(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LeaveTypeSeeder::class]);

        [$company, $user] = $this->companyAdmin();
        $employee = $this->employee($company);
        $leaveType = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

        EmployeeLeaveBalance::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'leave_year' => 2026,
            'accrued_days' => 1,
            'closing_balance' => 1,
        ]);

        Sanctum::actingAs($user);

        $leaveRequestId = $this->postJson('/api/leave-requests', [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-05-20',
            'end_date' => '2026-05-21',
        ])->assertCreated()->json('data.leave_request.id');

        $this->postJson("/api/leave-requests/{$leaveRequestId}/approve")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['leave_balance']);

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequestId,
            'status' => 'pending',
        ]);
    }

    public function test_company_admin_can_reject_leave_request_and_release_pending_balance(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LeaveTypeSeeder::class]);

        [$company, $user] = $this->companyAdmin();
        $employee = $this->employee($company);
        $leaveType = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

        Sanctum::actingAs($user);

        $leaveRequestId = $this->postJson('/api/leave-requests', [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-05-20',
            'end_date' => '2026-05-21',
            'reason' => 'Personal.',
        ])->assertCreated()->json('data.leave_request.id');

        $this->postJson("/api/leave-requests/{$leaveRequestId}/reject", [
            'rejection_reason' => 'Insufficient handover coverage.',
        ])->assertOk()
            ->assertJsonPath('data.leave_request.status', 'rejected')
            ->assertJsonPath('data.leave_request.rejection_reason', 'Insufficient handover coverage.');

        $this->assertDatabaseHas('employee_leave_balances', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'leave_year' => 2026,
            'pending_days' => 0,
            'used_days' => 0,
        ]);
    }

    public function test_leave_request_rejects_foreign_company_employee(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LeaveTypeSeeder::class]);

        [, $user] = $this->companyAdmin();
        $otherCompany = Company::query()->create(['name' => 'Other Company']);
        $foreignEmployee = $this->employee($otherCompany);
        $leaveType = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

        Sanctum::actingAs($user);

        $this->postJson('/api/leave-requests', [
            'employee_id' => $foreignEmployee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-05-20',
            'end_date' => '2026-05-22',
        ])->assertUnprocessable();
    }

    public function test_leave_request_rejects_dates_after_employee_termination(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LeaveTypeSeeder::class]);

        [$company, $user] = $this->companyAdmin();
        $employee = $this->employee($company, [
            'status' => 'terminated',
            'contract_end_date' => '2026-05-20',
        ]);
        $leaveType = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

        Sanctum::actingAs($user);

        $this->postJson('/api/leave-requests', [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-05-20',
            'end_date' => '2026-05-20',
        ])->assertCreated();

        $this->postJson('/api/leave-requests', [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-05-21',
            'end_date' => '2026-05-21',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.start_date.0', 'Leave cannot be requested after the employee termination date.');
    }

    public function test_annual_leave_excludes_configured_public_holidays_from_balance_days(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LeaveTypeSeeder::class]);

        [$company, $user] = $this->companyAdmin();
        $employee = $this->employee($company);
        $leaveType = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

        PublicHoliday::query()->create([
            'company_id' => $company->id,
            'name' => 'Public Holiday',
            'holiday_date' => '2026-05-19',
            'country_code' => 'AE',
            'paid' => true,
            'source' => 'government',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/leave-requests/day-count?'.http_build_query([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-05-18',
            'end_date' => '2026-05-20',
        ]))->assertOk()
            ->assertJsonPath('data.calculation.total_days', 3)
            ->assertJsonPath('data.calculation.working_days', 2)
            ->assertJsonPath('data.calculation.public_holidays_count', 1)
            ->assertJsonPath('data.calculation.day_calculation_json.excluded_public_holidays.0.name', 'Public Holiday');

        $this->postJson('/api/leave-requests', [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-05-18',
            'end_date' => '2026-05-20',
        ])->assertCreated()
            ->assertJsonPath('data.leave_request.total_days', '3.00')
            ->assertJsonPath('data.leave_request.working_days', '2.00')
            ->assertJsonPath('data.leave_request.public_holidays_count', '1.00')
            ->assertJsonPath('data.leave_request.day_calculation.excluded_public_holidays.0.date', '2026-05-19');

        $this->assertDatabaseHas('employee_leave_balances', [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'leave_year' => 2026,
            'pending_days' => 2,
        ]);
    }

    public function test_company_policy_can_count_public_holidays_as_annual_leave_days(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LeaveTypeSeeder::class, LegalRuleSeeder::class]);

        [$company, $user] = $this->companyAdmin();
        $employee = $this->employee($company);
        $leaveType = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();
        $ruleSet = LegalRuleSet::query()->firstOrFail();

        CompanyComplianceSetting::query()->create([
            'company_id' => $company->id,
            'legal_rule_set_id' => $ruleSet->id,
            'public_holidays_count_as_annual_leave' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        PublicHoliday::query()->create([
            'company_id' => $company->id,
            'name' => 'Company Counted Holiday',
            'holiday_date' => '2026-05-19',
            'country_code' => 'AE',
            'paid' => true,
            'source' => 'company',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/leave-requests', [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-05-18',
            'end_date' => '2026-05-20',
        ])->assertCreated()
            ->assertJsonPath('data.leave_request.working_days', '3.00')
            ->assertJsonPath('data.leave_request.public_holidays_count', '0.00')
            ->assertJsonPath('data.leave_request.day_calculation.public_holidays_count_as_annual_leave', true);
    }

    public function test_company_admin_can_configure_employee_leave_balance(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LeaveTypeSeeder::class]);

        [$company, $user] = $this->companyAdmin();
        $employee = $this->employee($company);
        $leaveType = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

        Sanctum::actingAs($user);

        $this->postJson('/api/leave-balances', [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'leave_year' => 2026,
            'opening_balance' => 2,
            'accrued_days' => 30,
            'carried_forward_days' => 1,
            'adjusted_days' => 0.5,
            'encashed_days' => 1,
            'note' => 'Initial annual leave entitlement.',
        ])->assertOk()
            ->assertJsonPath('data.leave_balance.closing_balance', '32.50');

        $this->assertDatabaseHas('employee_leave_balances', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'leave_year' => 2026,
            'closing_balance' => 32.5,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'leave_balance.updated',
        ]);
    }

    public function test_leave_balance_configuration_rejects_foreign_company_employee(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LeaveTypeSeeder::class]);

        [, $user] = $this->companyAdmin();
        $otherCompany = Company::query()->create(['name' => 'Other Company']);
        $foreignEmployee = $this->employee($otherCompany);
        $leaveType = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

        Sanctum::actingAs($user);

        $this->postJson('/api/leave-balances', [
            'employee_id' => $foreignEmployee->id,
            'leave_type_id' => $leaveType->id,
            'leave_year' => 2026,
            'opening_balance' => 0,
            'accrued_days' => 30,
            'carried_forward_days' => 0,
            'adjusted_days' => 0,
        ])->assertUnprocessable();
    }

    public function test_company_admin_can_run_uae_annual_leave_accrual(): void
    {
        Carbon::setTestNow('2026-12-31 12:00:00');
        $this->seed([RoleAndPermissionSeeder::class, LeaveTypeSeeder::class]);

        [$company, $user] = $this->companyAdmin();
        $newHire = $this->employee($company, ['employee_code' => 'EMP-NEW', 'hire_date' => '2026-08-01']);
        $midYearHire = $this->employee($company, ['employee_code' => 'EMP-MID', 'hire_date' => '2026-04-01']);
        $fullYearEmployee = $this->employee($company, ['employee_code' => 'EMP-FULL', 'hire_date' => '2025-12-31']);
        $leaveType = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

        Sanctum::actingAs($user);

        $this->postJson('/api/leave-balances/accrue-annual', [
            'leave_year' => 2026,
        ])->assertOk()
            ->assertJsonPath('data.processed_count', 3);

        $this->assertDatabaseHas('employee_leave_balances', [
            'employee_id' => $newHire->id,
            'leave_type_id' => $leaveType->id,
            'leave_year' => 2026,
            'accrued_days' => 0,
        ]);
        $this->assertDatabaseHas('employee_leave_balances', [
            'employee_id' => $midYearHire->id,
            'leave_type_id' => $leaveType->id,
            'leave_year' => 2026,
            'accrued_days' => 16,
        ]);
        $this->assertDatabaseHas('employee_leave_balances', [
            'employee_id' => $fullYearEmployee->id,
            'leave_type_id' => $leaveType->id,
            'leave_year' => 2026,
            'accrued_days' => 30,
        ]);
        $this->assertDatabaseCount('audit_logs', 3);

        Carbon::setTestNow();
    }

    public function test_annual_leave_accrual_caps_terminated_employee_at_contract_end_date(): void
    {
        Carbon::setTestNow('2026-12-31 12:00:00');
        $this->seed([RoleAndPermissionSeeder::class, LeaveTypeSeeder::class]);

        [$company, $user] = $this->companyAdmin();
        $terminatedThisYear = $this->employee($company, [
            'employee_code' => 'EMP-TERM-THIS',
            'hire_date' => '2026-01-01',
            'status' => 'terminated',
            'contract_end_date' => '2026-07-01',
        ]);
        $terminatedPreviousYear = $this->employee($company, [
            'employee_code' => 'EMP-TERM-OLD',
            'hire_date' => '2020-01-01',
            'status' => 'terminated',
            'contract_end_date' => '2025-12-31',
        ]);
        $leaveType = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

        Sanctum::actingAs($user);

        $this->postJson('/api/leave-balances/accrue-annual', [
            'leave_year' => 2026,
        ])->assertOk()
            ->assertJsonPath('data.processed_count', 1);

        $this->assertDatabaseHas('employee_leave_balances', [
            'employee_id' => $terminatedThisYear->id,
            'leave_type_id' => $leaveType->id,
            'leave_year' => 2026,
            'accrued_days' => 12,
        ]);
        $this->assertDatabaseMissing('employee_leave_balances', [
            'employee_id' => $terminatedPreviousYear->id,
            'leave_type_id' => $leaveType->id,
            'leave_year' => 2026,
        ]);

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

    private function employee(Company $company, array $overrides = []): Employee
    {
        return Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-'.$company->id,
            'first_name' => 'Noura',
            'last_name' => 'Ahmed',
            'display_name' => 'Noura Ahmed',
            'status' => 'active',
            ...$overrides,
        ]);
    }
}
