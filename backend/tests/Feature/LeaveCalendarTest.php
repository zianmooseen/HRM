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

class LeaveCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleAndPermissionSeeder::class, LeaveTypeSeeder::class]);
    }

    public function test_admin_sees_only_approved_overlapping_leave_in_their_company(): void
    {
        $company = Company::create(['name' => 'Calendar company']);
        $user = $this->userInCompany($company);
        $employee = $this->employee($company);
        $included = [];
        foreach ([['2025-12-20', '2026-01-01'], ['2026-01-31', '2026-02-04'], ['2025-12-01', '2026-02-28'], ['2026-01-15', '2026-01-15']] as [$start, $end]) {
            $included[] = $this->leave($employee, ['start_date' => $start, 'end_date' => $end])->id;
        }
        $this->leave($employee, ['start_date' => '2025-12-01', 'end_date' => '2025-12-31']);
        $this->leave($employee, ['start_date' => '2026-02-01', 'end_date' => '2026-02-02']);
        $this->leave($employee, ['status' => 'pending']);
        $this->leave($employee, ['status' => 'rejected']);
        $otherCompany = Company::create(['name' => 'Other company']);
        $this->leave($this->employee($otherCompany));
        $deletedEmployee = $this->employee($company);
        $this->leave($deletedEmployee);
        $deletedEmployee->delete();

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/leave-calendar?month=2026-01&company_id='.$otherCompany->id)
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.month', '2026-01')
            ->assertJsonCount(4, 'data.entries');
        $this->assertEqualsCanonicalizing($included, array_column($response->json('data.entries'), 'id'));
        $this->getJson('/api/leave-calendar?month=2027-01')->assertOk()->assertJsonPath('data.entries', []);
    }

    public function test_employee_sees_self_and_colleagues_but_only_safe_calendar_fields(): void
    {
        $company = Company::create(['name' => 'Calendar company']);
        $user = $this->userInCompany($company, 'employee');
        $self = $this->employee($company, ['user_id' => $user->id]);
        $colleague = $this->employee($company, ['display_name' => '', 'first_name' => 'Noura', 'last_name' => 'Ahmed']);
        $ownLeave = $this->leave($self);
        $colleagueLeave = $this->leave($colleague);

        Sanctum::actingAs($user);
        $entries = $this->getJson('/api/leave-calendar?month=2026-01')->assertOk()->assertJsonCount(2, 'data.entries')->json('data.entries');
        $this->assertSame([
            'id' => $ownLeave->id,
            'employee_id' => $self->id,
            'employee_name' => $self->display_name,
            'is_self' => true,
            'start_date' => '2026-01-10',
            'end_date' => '2026-01-12',
        ], $entries[0]);
        $this->assertSame([
            'id' => $colleagueLeave->id,
            'employee_id' => $colleague->id,
            'employee_name' => 'Noura Ahmed',
            'is_self' => false,
            'start_date' => '2026-01-10',
            'end_date' => '2026-01-12',
        ], $entries[1]);
        $this->getJson('/api/leave-requests')->assertOk()->assertJsonCount(1, 'data.leave_requests');
        $this->getJson('/api/leave-requests/'.$colleagueLeave->id)->assertForbidden();
    }

    public function test_access_requires_authentication_company_permission_and_employee_link(): void
    {
        $this->getJson('/api/leave-calendar?month=2026-01')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/leave-calendar?month=2026-01')->assertForbidden();
        $company = Company::create(['name' => 'Calendar company']);
        $user = $this->userInCompany($company, 'employee');
        Sanctum::actingAs($user);
        $this->getJson('/api/leave-calendar?month=2026-01')->assertForbidden();
        $other = Company::create(['name' => 'Other']);
        $this->employee($other, ['user_id' => $user->id]);
        $this->getJson('/api/leave-calendar?month=2026-01')->assertForbidden();
        $restricted = Role::create(['name' => 'Restricted', 'slug' => 'restricted']);
        $restrictedUser = User::factory()->create();
        $restrictedUser->roles()->attach($restricted->id, ['company_id' => $company->id, 'scope' => 'company']);
        $restrictedUser->roles()->attach(Role::where('slug', 'company_admin')->firstOrFail()->id, ['company_id' => $other->id, 'scope' => 'company']);
        Sanctum::actingAs($restrictedUser);
        $this->getJson('/api/leave-calendar?month=2026-01')->assertForbidden();
    }

    public function test_month_is_required_and_validated_and_leap_days_are_included(): void
    {
        $company = Company::create(['name' => 'Calendar company']);
        Sanctum::actingAs($this->userInCompany($company));
        foreach (['', '2026-13', '2026-00', '2026-1', 'not-a-month', '2026-01-01', '0000-01', '10000-01'] as $month) {
            $this->getJson('/api/leave-calendar?month='.$month)->assertUnprocessable()->assertJsonValidationErrors('month');
        }
        $this->leave($this->employee($company), ['start_date' => '2028-02-29', 'end_date' => '2028-03-01']);
        $this->getJson('/api/leave-calendar?month=2028-02')->assertOk()->assertJsonCount(1, 'data.entries');
    }

    private function userInCompany(Company $company, string $slug = 'company_admin'): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $slug)->firstOrFail()->id, [
            'company_id' => $company->id,
            'scope' => $slug === 'employee' ? 'self' : 'company',
        ]);

        return $user;
    }

    private function employee(Company $company, array $overrides = []): Employee
    {
        return Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-'.(Employee::withTrashed()->count() + 1),
            'first_name' => 'Sara',
            'last_name' => 'Ali',
            'display_name' => 'Sara Ali',
            'status' => 'active',
            ...$overrides,
        ]);
    }

    private function leave(Employee $employee, array $overrides = []): LeaveRequest
    {
        return LeaveRequest::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'leave_type_id' => LeaveType::where('code', 'annual_leave')->firstOrFail()->id,
            'start_date' => '2026-01-10',
            'end_date' => '2026-01-12',
            'total_days' => 3,
            'working_days' => 3,
            'status' => 'approved',
            'requested_by' => User::query()->firstOrFail()->id,
            'reason' => 'Private medical information',
            'approval_note' => 'Private HR note',
            ...$overrides,
        ]);
    }
}
