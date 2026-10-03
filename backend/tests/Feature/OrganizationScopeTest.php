<?php

namespace Tests\Feature;

use App\Models;
use App\Services\Auth\OrganizationContext;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationScopeTest extends TestCase
{
    use RefreshDatabase;

    private Models\Company $company;

    private Models\Company $otherCompany;

    private Models\Branch $branch;

    private Models\Branch $otherBranch;

    private Models\Branch $foreignBranch;

    private Models\User $admin;

    private array $employees;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleAndPermissionSeeder::class, LeaveTypeSeeder::class]);
        $this->company = Models\Company::create(['name' => 'Company A']);
        $this->otherCompany = Models\Company::create(['name' => 'Company B']);
        $this->branch = $this->company->branches()->create(['name' => 'Dubai', 'code' => 'DXB']);
        $this->otherBranch = $this->company->branches()->create(['name' => 'Abu Dhabi', 'code' => 'AUH']);
        $this->foreignBranch = $this->otherCompany->branches()->create(['name' => 'Sharjah', 'code' => 'SHJ']);
        $this->admin = $this->user('company_admin', $this->company);
        $this->employees = [];
        foreach ([$this->branch, $this->otherBranch, $this->foreignBranch] as $index => $branch) {
            $this->employees[] = Models\Employee::create([
                'company_id' => $branch->company_id, 'branch_id' => $branch->id,
                'employee_code' => 'EMP-'.$index, 'first_name' => 'Employee',
                'last_name' => (string) $index, 'display_name' => 'Employee '.$index, 'status' => 'active',
            ]);
        }
        Sanctum::actingAs($this->admin);
    }

    public function test_filter_options_only_list_authorized_companies_and_their_branches(): void
    {
        $this->getJson('/api/organization-scope')->assertOk()->assertJsonCount(1, 'data.companies')
            ->assertJsonCount(2, 'data.companies.0.branches');
        Sanctum::actingAs($this->user('super_admin'));
        $this->getJson('/api/organization-scope')->assertOk()->assertJsonCount(2, 'data.companies');
    }

    public function test_scope_filters_employee_lists_details_dashboard_and_resets_between_requests(): void
    {
        $this->getJson($this->url('/employees'))->assertOk()->assertJsonCount(1, 'data.employees')
            ->assertJsonPath('data.employees.0.id', $this->employees[0]->id);
        $this->getJson($this->url('/employees/'.$this->employees[1]->id))->assertNotFound();
        $this->getJson($this->url('/employees/'.$this->employees[2]->id))->assertNotFound();
        $this->getJson($this->url('/dashboard'))->assertOk()
            ->assertJsonPath('data.dashboard.scope.branch.id', $this->branch->id)
            ->assertJsonPath('data.dashboard.employee_counts.total', 1);
        $this->getJson('/api/employees?scope_company_id='.$this->company->id)->assertOk()->assertJsonCount(2, 'data.employees');
        $this->getJson('/api/employees')->assertOk()->assertJsonCount(2, 'data.employees');
        $this->assertNull(OrganizationContext::current());
    }

    public function test_super_admin_can_select_another_company_across_modules_and_calendar(): void
    {
        Sanctum::actingAs($this->user('super_admin'));
        $query = '?scope_company_id='.$this->otherCompany->id.'&scope_branch_id='.$this->foreignBranch->id;
        $this->getJson('/api/company'.$query)->assertOk()->assertJsonPath('data.company.id', $this->otherCompany->id);
        $this->getJson('/api/companies'.$query)->assertOk()->assertJsonCount(1, 'data.companies');
        $this->getJson('/api/employees'.$query)->assertOk()->assertJsonCount(1, 'data.employees')
            ->assertJsonPath('data.employees.0.id', $this->employees[2]->id);
        $this->getJson('/api/leave-calendar'.$query.'&month=2026-09')->assertOk();
        $this->getJson('/api/platform/wps-risk'.$query)->assertOk()->assertJsonCount(1, 'data.companies');
    }

    public function test_invalid_or_unauthorized_scope_cannot_be_used(): void
    {
        $this->getJson('/api/employees?scope_company_id='.$this->otherCompany->id)->assertForbidden();
        $this->getJson('/api/employees?scope_company_id='.$this->company->id.'&scope_branch_id='.$this->foreignBranch->id)->assertNotFound();
        $this->getJson('/api/employees?scope_company_id=oops')->assertUnprocessable();
        $this->getJson('/api/employees?scope_branch_id='.$this->branch->id)->assertUnprocessable();
        Sanctum::actingAs($this->user('employee', $this->company));
        $this->getJson($this->url('/employees'))->assertForbidden();
        $this->getJson('/api/organization-scope')->assertForbidden();
    }

    public function test_branch_selection_filters_leave_attendance_documents_onboarding_and_balances(): void
    {
        foreach ($this->employees as $employee) {
            $base = ['company_id' => $employee->company_id, 'employee_id' => $employee->id];
            Models\LeaveRequest::create([...$base, 'leave_type_id' => Models\LeaveType::first()->id,
                'start_date' => '2026-09-01', 'end_date' => '2026-09-03', 'total_days' => 3, 'working_days' => 3,
                'status' => 'approved', 'requested_by' => $this->admin->id]);
            Models\AttendanceRecord::create([...$base, 'date' => '2026-09-01', 'status' => 'present', 'source' => 'manual']);
            Models\EmployeeLeaveBalance::create([...$base, 'leave_type_id' => Models\LeaveType::first()->id, 'leave_year' => 2026]);
            Models\EmployeeOnboardingCase::create([...$base, 'status' => 'in_progress']);
            Models\Document::create([...$base, 'document_type' => 'passport', 'title' => 'Passport',
                'original_file_name' => 'passport.pdf', 'disk' => 'local', 'path' => 'private/passport.pdf',
                'mime_type' => 'application/pdf', 'size_bytes' => 20, 'uploaded_by' => $this->admin->id]);
        }
        foreach (['/leave-requests' => 'leave_requests', '/leave-calendar?month=2026-09' => 'entries',
            '/attendance-records' => 'attendance_records', '/documents' => 'documents',
            '/onboarding-cases' => 'onboarding_cases', '/leave-balances' => 'leave_balances'] as $path => $key) {
            $this->getJson($this->url($path))->assertOk()->assertJsonCount(1, 'data.'.$key)
                ->assertJsonPath('data.'.$key.'.0.employee_id', $this->employees[0]->id);
        }
        $foreignDocument = Models\Document::where('employee_id', $this->employees[1]->id)->first();
        $this->getJson($this->url('/documents/'.$foreignDocument->id.'/download'))->assertNotFound();
    }

    public function test_branch_payroll_view_filters_nested_payslips_without_allowing_partial_payroll_runs(): void
    {
        $period = $this->company->payrollPeriods()->create(['period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'status' => 'processed']);
        foreach (array_slice($this->employees, 0, 2) as $employee) {
            $period->payslips()->create(['company_id' => $this->company->id, 'employee_id' => $employee->id, 'net_pay' => 1000]);
        }
        $this->getJson($this->url('/payroll-periods'))->assertOk()->assertJsonPath('data.payroll_periods.0.payslips_count', 1);
        $this->getJson($this->url('/payroll-periods/'.$period->id))->assertOk()->assertJsonCount(1, 'data.payslips')
            ->assertJsonPath('data.payslips.0.employee_id', $this->employees[0]->id);
        $this->postJson($this->url('/payroll-periods/'.$period->id.'/run'))->assertUnprocessable();
        $this->postJson($this->url('/payroll-periods/'.$period->id.'/approve'))->assertUnprocessable();
        $this->assertDatabaseHas('payroll_periods', ['id' => $period->id, 'status' => 'processed']);
        $this->assertDatabaseCount('payslips', 2);
    }

    public function test_branch_writes_default_to_selected_branch_and_cannot_modify_other_branches(): void
    {
        $data = ['employee_code' => 'NEW', 'first_name' => 'New', 'last_name' => 'Employee', 'status' => 'active'];
        $this->postJson($this->url('/employees'), $data)->assertCreated()->assertJsonPath('data.employee.branch_id', $this->branch->id);
        $this->postJson($this->url('/employees'), [...$data, 'branch_id' => $this->otherBranch->id])->assertUnprocessable();
        $this->postJson($this->url('/employees'), [...$data, 'employee_code' => $this->employees[1]->employee_code])->assertUnprocessable()->assertJsonValidationErrors('employee_code');
        $this->deleteJson($this->url('/employees/'.$this->employees[1]->id))->assertNotFound();
        $this->postJson($this->url('/leave-requests'), ['employee_id' => $this->employees[1]->id])->assertUnprocessable();
        $this->assertDatabaseHas('employees', ['id' => $this->employees[1]->id, 'deleted_at' => null]);
    }

    public function test_wps_totals_and_items_are_filtered_but_complete_files_cannot_escape_branch_view(): void
    {
        $period = $this->company->payrollPeriods()->create(['period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'status' => 'approved']);
        $batch = $this->company->wpsPayrollBatches()->create(['payroll_period_id' => $period->id,
            'batch_number' => 'WPS-ONE', 'salary_month' => '2026-09', 'record_count' => 2, 'total_amount' => 3000,
            'file_content' => 'Entire company payroll', 'response_details_json' => ['other_employee' => 'private']]);
        foreach (array_slice($this->employees, 0, 2) as $index => $employee) {
            $payslip = $period->payslips()->create(['company_id' => $this->company->id, 'employee_id' => $employee->id, 'net_pay' => ($index + 1) * 1000]);
            $batch->items()->create(['payslip_id' => $payslip->id, 'employee_id' => $employee->id,
                'employee_code' => $employee->employee_code, 'employee_name' => $employee->display_name,
                'bank_iban' => 'AE000000000000000000000', 'bank_routing_code' => 'TEST', 'wps_person_id' => 'TEST',
                'net_pay' => $payslip->net_pay, 'row_payload_json' => []]);
        }
        $this->getJson($this->url('/wps-payroll-batches'))->assertOk()
            ->assertJsonPath('data.wps_payroll_batches.0.record_count', 1)
            ->assertJsonPath('data.wps_payroll_batches.0.total_amount', '1000.00');
        $this->getJson($this->url('/wps-payroll-batches/'.$batch->id))->assertOk()
            ->assertJsonCount(1, 'data.wps_payroll_batch.items')
            ->assertJsonPath('data.wps_payroll_batch.response_details', null);
        $this->getJson($this->url('/wps-payroll-batches/'.$batch->id.'/download'))->assertUnprocessable();
        $this->get('/api/wps-payroll-batches/'.$batch->id.'/download?scope_company_id='.$this->company->id)->assertOk();
        $this->assertDatabaseHas('wps_payroll_batches', ['id' => $batch->id, 'record_count' => 2, 'total_amount' => 3000]);
    }

    public function test_platform_billing_is_filtered_by_company_including_direct_actions(): void
    {
        Sanctum::actingAs($this->user('super_admin'));
        $invoices = [];
        foreach ([$this->company, $this->otherCompany] as $index => $company) {
            $invoices[] = Models\BillingInvoice::create(['company_id' => $company->id, 'invoice_number' => 'INV-'.$index,
                'issue_date' => '2026-09-01', 'due_date' => '2026-09-30', 'subtotal' => 100, 'total_amount' => 100]);
        }
        $this->getJson($this->url('/platform/billing/invoices'))->assertOk()->assertJsonCount(1, 'data.billing_invoices')
            ->assertJsonPath('data.billing_invoices.0.company_id', $this->company->id);
        $this->postJson($this->url('/platform/billing/invoices/'.$invoices[1]->id.'/mark-paid'))->assertNotFound();
        $this->assertDatabaseHas('billing_invoices', ['id' => $invoices[1]->id, 'paid_at' => null]);
    }

    public function test_company_compliance_counts_do_not_turn_into_branch_obligations(): void
    {
        $this->getJson($this->url('/compliance/emiratisation'))->assertOk()->assertJsonPath('data.snapshot.total_active_workers', 2);
        $this->postJson($this->url('/compliance/emiratisation/snapshot'))->assertUnprocessable();
    }

    public function test_shared_departments_are_visible_but_other_branches_are_excluded(): void
    {
        foreach ([null, $this->branch->id, $this->otherBranch->id] as $index => $branchId) {
            $this->company->departments()->create(['name' => 'Department '.$index, 'code' => 'DEP-'.$index, 'branch_id' => $branchId]);
        }
        $this->getJson($this->url('/departments'))->assertOk()->assertJsonCount(2, 'data.departments');
        $this->getJson($this->url('/branches'))->assertOk()->assertJsonCount(1, 'data.branches');
    }

    public function test_audit_lists_and_csv_exports_respect_branch_selection(): void
    {
        foreach ($this->employees as $employee) {
            Models\AuditLog::create(['company_id' => $employee->company_id, 'actor_user_id' => $this->admin->id,
                'action' => 'leave_request.created', 'auditable_type' => Models\LeaveRequest::class,
                'auditable_id' => 1, 'after_json' => ['employee_id' => $employee->id]]);
        }
        $this->getJson($this->url('/audit-logs'))->assertOk()->assertJsonCount(1, 'data.audit_logs')->assertJsonPath('data.meta.total', 1);
        $this->getJson($this->url('/compliance/reports'))->assertOk()->assertJsonCount(1, 'data.summary.recent_audit_logs');
        $csv = $this->get($this->url('/compliance/reports/export?type=audit'))->assertOk()->streamedContent();
        $this->assertSame(1, substr_count($csv, 'leave_request.created'));
    }

    private function user(string $role, ?Models\Company $company = null): Models\User
    {
        $user = Models\User::factory()->create();
        $user->roles()->attach(Models\Role::where('slug', $role)->firstOrFail()->id, [
            'company_id' => $company?->id, 'scope' => $company ? 'company' : 'global',
        ]);

        return $user;
    }

    private function url(string $path): string
    {
        return '/api'.$path.(str_contains($path, '?') ? '&' : '?').'scope_company_id='.$this->company->id.'&scope_branch_id='.$this->branch->id;
    }
}
