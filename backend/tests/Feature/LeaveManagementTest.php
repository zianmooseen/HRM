<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
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
            ->assertJsonPath('data.leave_request.status', 'approved');

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
            'employee_code' => 'EMP-'.$company->id,
            'first_name' => 'Noura',
            'last_name' => 'Ahmed',
            'display_name' => 'Noura Ahmed',
            'status' => 'active',
        ]);
    }
}
