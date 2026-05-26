<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Document;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_create_employee_login_account(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        [$company, $admin] = $this->companyAdmin();
        $employee = $this->employee($company);
        Sanctum::actingAs($admin);

        $this->postJson("/api/employees/{$employee->id}/account", [
            'username' => 'emp.one',
            'email' => 'emp.one@example.test',
            'password' => 'employee1',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.username', 'emp.one')
            ->assertJsonPath('data.employee.user_id', 2);

        $this->assertDatabaseHas('users', ['username' => 'emp.one']);
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'user_id' => 2]);
        $this->assertDatabaseHas('user_roles', [
            'user_id' => 2,
            'company_id' => $company->id,
            'scope' => 'self',
        ]);
    }

    public function test_employee_self_service_is_limited_to_own_records(): void
    {
        Storage::fake('local');
        $this->seed([RoleAndPermissionSeeder::class, LeaveTypeSeeder::class]);

        $company = Company::query()->create(['name' => 'Demo Company']);
        $employeeUser = User::factory()->create(['username' => 'self.emp']);
        $otherUser = User::factory()->create();
        $employee = $this->employee($company, ['user_id' => $employeeUser->id, 'employee_code' => 'SELF-1']);
        $otherEmployee = $this->employee($company, ['user_id' => $otherUser->id, 'employee_code' => 'SELF-2']);
        $role = Role::query()->where('slug', 'employee')->firstOrFail();
        $employeeUser->roles()->attach($role->id, [
            'company_id' => $company->id,
            'scope' => 'self',
        ]);
        $leaveType = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

        LeaveRequest::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-02',
            'total_days' => 2,
            'working_days' => 2,
            'status' => 'pending',
            'requested_by' => $employeeUser->id,
        ]);
        LeaveRequest::query()->create([
            'company_id' => $company->id,
            'employee_id' => $otherEmployee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-02',
            'total_days' => 2,
            'working_days' => 2,
            'status' => 'pending',
            'requested_by' => $otherUser->id,
        ]);
        AttendanceRecord::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-06-01',
            'status' => 'present',
            'source' => 'manual',
        ]);
        AttendanceRecord::query()->create([
            'company_id' => $company->id,
            'employee_id' => $otherEmployee->id,
            'date' => '2026-06-01',
            'status' => 'present',
            'source' => 'manual',
        ]);
        $document = Document::query()->create([
            'company_id' => $company->id,
            'employee_id' => $otherEmployee->id,
            'document_type' => 'passport',
            'title' => 'Other passport',
            'original_file_name' => 'other.pdf',
            'disk' => 'local',
            'path' => 'other.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'uploaded_by' => $otherUser->id,
        ]);
        Storage::disk('local')->put('other.pdf', 'x');

        Sanctum::actingAs($employeeUser);

        $this->getJson('/api/self/profile')
            ->assertOk()
            ->assertJsonPath('data.employee.id', $employee->id);

        $this->getJson('/api/leave-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data.leave_requests')
            ->assertJsonPath('data.leave_requests.0.employee_id', $employee->id);

        $this->postJson('/api/leave-requests', [
            'employee_id' => $otherEmployee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-02',
        ])->assertForbidden();

        $this->getJson('/api/attendance-records')
            ->assertOk()
            ->assertJsonCount(1, 'data.attendance_records')
            ->assertJsonPath('data.attendance_records.0.employee_id', $employee->id);

        $this->getJson('/api/documents')
            ->assertOk()
            ->assertJsonCount(0, 'data.documents');

        $this->get("/api/documents/{$document->id}/download")
            ->assertForbidden();

        $this->post('/api/documents', [
            'employee_id' => $employee->id,
            'document_type' => 'passport',
            'file' => UploadedFile::fake()->create('passport.pdf', 10, 'application/pdf'),
        ])->assertCreated();
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
            'first_name' => 'Sara',
            'last_name' => 'Hassan',
            'display_name' => 'Sara Hassan',
            'status' => 'active',
            'hire_date' => '2025-01-01',
            ...$overrides,
        ]);
    }
}
