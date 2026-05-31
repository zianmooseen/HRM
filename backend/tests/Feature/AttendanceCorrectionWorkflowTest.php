<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceCorrectionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_submit_and_approve_attendance_correction(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        [$company, $admin] = $this->companyAdmin();
        $employee = $this->employee($company, 'COR-001');
        $record = AttendanceRecord::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-05-20',
            'check_in' => '09:30',
            'check_out' => '18:00',
            'break_minutes' => 60,
            'status' => 'late',
            'source' => 'manual',
        ]);

        Sanctum::actingAs($admin);

        $correctionId = $this->postJson('/api/attendance-correction-requests', [
            'employee_id' => $employee->id,
            'attendance_record_id' => $record->id,
            'date' => '2026-05-20',
            'correction_type' => 'wrong_time',
            'requested_check_in' => '09:00',
            'requested_check_out' => '18:00',
            'requested_break_minutes' => 60,
            'requested_status' => 'present',
            'reason' => 'Badge reader delay.',
        ])->assertCreated()
            ->assertJsonPath('data.attendance_correction_request.status', 'pending')
            ->json('data.attendance_correction_request.id');

        $this->postJson("/api/attendance-correction-requests/{$correctionId}/approve")
            ->assertOk()
            ->assertJsonPath('data.attendance_correction_request.status', 'approved');

        $this->assertDatabaseHas('attendance_records', [
            'id' => $record->id,
            'check_in' => '09:00',
            'status' => 'present',
            'source' => 'correction',
        ]);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'attendance_correction.submitted']);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'attendance_correction.approved']);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'attendance.updated']);
    }

    public function test_approval_creates_missing_attendance_record(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        [$company, $admin] = $this->companyAdmin();
        $employee = $this->employee($company, 'COR-002');

        Sanctum::actingAs($admin);

        $correctionId = $this->postJson('/api/attendance-correction-requests', [
            'employee_id' => $employee->id,
            'date' => '2026-05-21',
            'correction_type' => 'missed_check_in',
            'requested_check_in' => '09:00',
            'requested_check_out' => '18:00',
            'requested_status' => 'present',
            'reason' => 'Forgot to punch in.',
        ])->assertCreated()
            ->json('data.attendance_correction_request.id');

        $this->postJson("/api/attendance-correction-requests/{$correctionId}/approve")
            ->assertOk();

        $this->assertDatabaseHas('attendance_records', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-05-21 00:00:00',
            'check_in' => '09:00',
            'status' => 'present',
            'source' => 'correction',
        ]);
    }

    public function test_company_admin_can_reject_attendance_correction(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        [$company, $admin] = $this->companyAdmin();
        $employee = $this->employee($company, 'COR-003');
        $correction = AttendanceCorrectionRequest::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-05-22',
            'correction_type' => 'other',
            'requested_break_minutes' => 0,
            'requested_status' => 'present',
            'reason' => 'Need correction.',
            'status' => 'pending',
            'requested_by' => $admin->id,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/attendance-correction-requests/{$correction->id}/reject", [
            'rejection_reason' => 'Manager could not verify the request.',
        ])->assertOk()
            ->assertJsonPath('data.attendance_correction_request.status', 'rejected')
            ->assertJsonPath('data.attendance_correction_request.rejection_reason', 'Manager could not verify the request.');

        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'attendance_correction.rejected']);
    }

    public function test_employee_self_service_can_only_submit_own_correction(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $company = Company::query()->create(['name' => 'Demo Company']);
        $user = User::factory()->create();
        $employeeRole = Role::query()->where('slug', 'employee')->firstOrFail();
        $user->roles()->attach($employeeRole->id, ['company_id' => $company->id, 'scope' => 'self']);
        $ownEmployee = $this->employee($company, 'SELF-001', ['user_id' => $user->id]);
        $otherEmployee = $this->employee($company, 'SELF-002');

        Sanctum::actingAs($user);

        $this->postJson('/api/attendance-correction-requests', [
            'employee_id' => $otherEmployee->id,
            'date' => '2026-05-23',
            'correction_type' => 'missed_check_out',
            'requested_check_in' => '09:00',
            'requested_check_out' => '18:00',
            'requested_status' => 'present',
            'reason' => 'Forgot to punch out.',
        ])->assertCreated()
            ->assertJsonPath('data.attendance_correction_request.employee_id', $ownEmployee->id);

        $this->getJson('/api/attendance-correction-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data.attendance_correction_requests')
            ->assertJsonPath('data.attendance_correction_requests.0.employee_id', $ownEmployee->id);
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

    private function employee(Company $company, string $code, array $overrides = []): Employee
    {
        return Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => $code,
            'first_name' => 'Attendance',
            'last_name' => 'Employee',
            'display_name' => $code,
            'status' => 'active',
            ...$overrides,
        ]);
    }
}
