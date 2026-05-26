<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_manage_attendance_for_assigned_company(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        [$company, $user] = $this->companyAdmin();
        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-100',
            'first_name' => 'Mariam',
            'last_name' => 'Saeed',
            'display_name' => 'Mariam Saeed',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $createResponse = $this->postJson('/api/attendance-records', [
            'employee_id' => $employee->id,
            'date' => '2026-05-20',
            'check_in' => '09:00',
            'check_out' => '18:00',
            'break_minutes' => 60,
            'status' => 'present',
            'source' => 'manual',
            'notes' => 'Created by HR.',
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.attendance_record.status', 'present');

        $recordId = $createResponse->json('data.attendance_record.id');

        $this->getJson('/api/attendance-records')
            ->assertOk()
            ->assertJsonCount(1, 'data.attendance_records')
            ->assertJsonPath('data.attendance_records.0.employee.employee_code', 'EMP-100');

        $this->putJson("/api/attendance-records/{$recordId}", [
            'employee_id' => $employee->id,
            'date' => '2026-05-20',
            'check_in' => '09:15',
            'check_out' => '18:00',
            'break_minutes' => 45,
            'status' => 'late',
            'source' => 'manual',
            'notes' => 'Adjusted late check-in.',
        ])->assertOk()
            ->assertJsonPath('data.attendance_record.status', 'late');

        $this->deleteJson("/api/attendance-records/{$recordId}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('attendance_records', ['id' => $recordId]);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'attendance.created']);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'attendance.updated']);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'attendance.deleted']);
    }

    public function test_attendance_create_rejects_foreign_company_employee(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        [, $user] = $this->companyAdmin();
        $otherCompany = Company::query()->create(['name' => 'Other Company']);
        $foreignEmployee = Employee::query()->create([
            'company_id' => $otherCompany->id,
            'employee_code' => 'OTHER-100',
            'first_name' => 'Foreign',
            'last_name' => 'Employee',
            'display_name' => 'Foreign Employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/attendance-records', [
            'employee_id' => $foreignEmployee->id,
            'date' => '2026-05-20',
            'check_in' => '09:00',
            'check_out' => '18:00',
            'status' => 'present',
            'source' => 'manual',
        ])->assertUnprocessable();
    }

    public function test_attendance_prevents_duplicate_employee_date_records(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        [$company, $user] = $this->companyAdmin();
        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-101',
            'first_name' => 'Ali',
            'last_name' => 'Hassan',
            'display_name' => 'Ali Hassan',
            'status' => 'active',
        ]);

        AttendanceRecord::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-05-20',
            'check_in' => '09:00',
            'check_out' => '18:00',
            'break_minutes' => 60,
            'status' => 'present',
            'source' => 'manual',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/attendance-records', [
            'employee_id' => $employee->id,
            'date' => '2026-05-20',
            'check_in' => '09:30',
            'check_out' => '18:00',
            'status' => 'late',
            'source' => 'manual',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.date.0', 'Attendance already exists for this employee and date.');
    }

    public function test_attendance_allows_terminated_employee_on_last_working_date_only(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        [$company, $user] = $this->companyAdmin();
        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-TERM',
            'first_name' => 'Terminated',
            'last_name' => 'Employee',
            'display_name' => 'Terminated Employee',
            'status' => 'terminated',
            'contract_end_date' => '2026-05-20',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/attendance-records', [
            'employee_id' => $employee->id,
            'date' => '2026-05-20',
            'check_in' => '09:00',
            'check_out' => '18:00',
            'status' => 'present',
            'source' => 'manual',
        ])->assertCreated();

        $this->postJson('/api/attendance-records', [
            'employee_id' => $employee->id,
            'date' => '2026-05-21',
            'check_in' => '09:00',
            'check_out' => '18:00',
            'status' => 'present',
            'source' => 'manual',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.date.0', 'Attendance cannot be recorded after the employee termination date.');
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
