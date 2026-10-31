<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeMenuViewsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 3));
        $this->seed(RoleAndPermissionSeeder::class);
        $this->company = Company::create(['name' => 'Own company', 'legal_name' => 'Own company LLC']);
        $this->user = User::factory()->create();
        $this->user->roles()->attach(Role::where('slug', 'employee')->firstOrFail()->id, ['company_id' => $this->company->id, 'scope' => 'self']);
        $this->employee = $this->employee($this->company, 'SELF', [
            'user_id' => $this->user->id, 'basic_salary' => 4000, 'monthly_salary' => 5000,
            'bank_name' => 'Employee bank', 'bank_iban' => 'AE070331234567890123456',
        ]);
        Sanctum::actingAs($this->user);
    }

    public function test_company_and_directory_are_scoped_and_publish_only_work_contact_fields(): void
    {
        $branch = Branch::create(['company_id' => $this->company->id, 'name' => 'Dubai office', 'code' => 'DXB', 'phone' => '+971 4 1234567']);
        $this->employee->update(['branch_id' => $branch->id]);
        $job = JobTitle::create(['company_id' => $this->company->id, 'title' => 'Engineer', 'code' => 'ENG']);
        $colleague = $this->employee($this->company, 'COLLEAGUE', [
            'display_name' => 'Amal Engineer', 'work_email' => 'amal@company.test', 'personal_email' => 'private@example.test',
            'phone' => 'private-mobile', 'basic_salary' => 9000, 'job_title_id' => $job->id, 'branch_id' => $branch->id,
        ]);
        $this->employee($this->company, 'FORMER', ['status' => 'terminated']);
        $otherCompany = Company::create(['name' => 'Other company']);
        $this->employee($otherCompany, 'OUTSIDE');
        $this->getJson('/api/self/company?company_id='.$otherCompany->id)->assertOk()
            ->assertJsonPath('data.company.name', 'Own company')->assertJsonPath('data.company.branch.phone', '+971 4 1234567');
        $response = $this->getJson('/api/self/directory?company_id='.$otherCompany->id)->assertOk()->assertJsonCount(2, 'data.employees');
        $this->assertEqualsCanonicalizing([$this->employee->id, $colleague->id], array_column($response->json('data.employees'), 'id'));
        foreach ($response->json('data.employees') as $person) {
            $this->assertEqualsCanonicalizing(['id', 'name', 'position', 'department', 'branch', 'work_email', 'office_phone'], array_keys($person));
        }
        $this->assertStringNotContainsString('private@example.test', $response->getContent());
        $this->assertStringNotContainsString('private-mobile', $response->getContent());
        $this->getJson('/api/self/directory?search=AMAL')->assertOk()->assertJsonCount(1, 'data.employees')
            ->assertJsonPath('data.employees.0.position', 'Engineer')->assertJsonPath('data.employees.0.work_email', 'amal@company.test');
        $this->getJson('/api/employees')->assertForbidden();
    }

    public function test_pay_views_include_only_own_approved_statements_and_year_to_date_values(): void
    {
        $older = $this->statement($this->employee, '2026-01-31', 5000, 500, 'approved');
        $latest = $this->statement($this->employee, '2026-09-30', 6000, 600, 'approved');
        $latest->items()->create(['label' => 'Basic salary', 'type' => 'earning', 'amount' => 6000, 'metadata_json' => ['internal' => 'hidden']]);
        $this->statement($this->employee, '2025-12-31', 1000, 100, 'approved');
        $this->statement($this->employee, '2026-10-31', 99000, 0, 'draft');
        $this->statement($this->employee, '2026-08-31', 99000, 0, 'approved', 'processed');
        $other = $this->employee($this->company, 'OTHER');
        $this->statement($other, '2026-10-01', 80000, 0, 'approved');
        $response = $this->getJson('/api/self/pay?year=2026&employee_id='.$other->id)->assertOk()
            ->assertJsonPath('data.current_statement.id', $latest->id)
            ->assertJsonPath('data.compensation.basic_salary', '4000.00')
            ->assertJsonPath('data.ytd.statements', 2)
            ->assertJsonPath('data.ytd.gross_pay', '11000.00')
            ->assertJsonPath('data.ytd.total_deductions', '1100.00')
            ->assertJsonPath('data.ytd.net_pay', '9900.00')
            ->assertJsonPath('data.direct_deposit.masked_iban', '•••• 3456');
        $this->assertStringNotContainsString('AE070331234567890123456', $response->getContent());
        $this->assertStringNotContainsString('metadata', $response->getContent());
        $this->getJson('/api/self/pay/history?employee_id='.$other->id)->assertOk()->assertJsonCount(3, 'data.statements')
            ->assertJsonPath('data.statements.0.id', $latest->id)->assertJsonPath('data.statements.1.id', $older->id);
        $this->getJson('/api/self/pay?year=2024')->assertOk()->assertJsonPath('data.ytd.net_pay', '0.00');
        $this->getJson('/api/payroll-periods')->assertForbidden();
        $this->getJson('/api/self/profile')->assertOk()->assertJsonMissingPath('data.employee.basic_salary');
    }

    public function test_future_approved_pay_is_excluded_from_ytd_and_empty_history_is_supported(): void
    {
        $this->getJson('/api/self/pay')->assertOk()->assertJsonPath('data.current_statement', null)->assertJsonPath('data.ytd.statements', 0);
        $this->getJson('/api/self/pay/history')->assertOk()->assertJsonCount(0, 'data.statements');
        $this->statement($this->employee, '2026-12-31', 9000, 0, 'approved');
        $this->getJson('/api/self/pay?year=2026')->assertOk()->assertJsonPath('data.ytd.gross_pay', '0.00');
    }

    public function test_unlinked_accounts_fail_closed_and_invalid_inputs_are_rejected(): void
    {
        $this->getJson('/api/self/pay?year=invalid')->assertUnprocessable();
        $this->getJson('/api/self/pay/history?page=0')->assertUnprocessable();
        $this->getJson('/api/self/directory?search[]=bad')->assertUnprocessable();
        Sanctum::actingAs(User::factory()->create());
        foreach (['company', 'directory', 'pay', 'pay/history'] as $path) {
            $this->getJson('/api/self/'.$path)->assertNotFound();
        }
    }

    public function test_employee_document_access_requires_a_linked_employee(): void
    {
        $unlinked = User::factory()->create();
        $unlinked->roles()->attach(Role::where('slug', 'employee')->firstOrFail()->id, ['company_id' => $this->company->id, 'scope' => 'self']);
        Sanctum::actingAs($unlinked);
        $this->getJson('/api/documents')->assertForbidden();
    }

    private function employee(Company $company, string $code, array $attributes = []): Employee
    {
        return Employee::create(['company_id' => $company->id, 'employee_code' => $code, 'first_name' => 'Employee', 'last_name' => $code, 'display_name' => 'Employee '.$code, 'status' => 'active', ...$attributes]);
    }

    private function statement(Employee $employee, string $date, int $gross, int $deductions, string $status, ?string $periodStatus = null): Payslip
    {
        $period = PayrollPeriod::create(['company_id' => $employee->company_id, 'period_start' => substr($date, 0, 8).'01', 'period_end' => $date, 'pay_date' => $date, 'status' => $periodStatus ?? $status]);

        return Payslip::create(['company_id' => $employee->company_id, 'employee_id' => $employee->id, 'payroll_period_id' => $period->id, 'gross_pay' => $gross, 'total_deductions' => $deductions, 'net_pay' => $gross - $deductions, 'status' => $status]);
    }
}
