<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WpsPayrollComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_payroll_manager_can_generate_wps_batch_for_approved_payroll(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        [$company, $user] = $this->payrollManager();
        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-WPS',
            'first_name' => 'Wps',
            'last_name' => 'Employee',
            'display_name' => 'Wps Employee',
            'status' => 'active',
            'basic_salary' => 10000,
            'bank_name' => 'Demo Bank',
            'bank_iban' => 'AE070331234567890123456',
            'bank_routing_code' => 'DEMOAEAD',
            'wps_person_id' => 'P123456',
        ]);
        $period = PayrollPeriod::query()->create([
            'company_id' => $company->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'pay_date' => '2026-06-01',
            'status' => 'approved',
        ]);
        Payslip::query()->create([
            'company_id' => $company->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'gross_pay' => 12000,
            'total_deductions' => 500,
            'net_pay' => 11500,
            'status' => 'approved',
        ]);

        Sanctum::actingAs($user);

        $batchId = $this->postJson("/api/payroll-periods/{$period->id}/wps-export")
            ->assertCreated()
            ->assertJsonPath('data.wps_payroll_batch.status', 'generated')
            ->assertJsonPath('data.wps_payroll_batch.record_count', 1)
            ->assertJsonPath('data.wps_payroll_batch.total_amount', '11500.00')
            ->json('data.wps_payroll_batch.id');

        $this->assertDatabaseHas('wps_payroll_batches', [
            'id' => $batchId,
            'company_id' => $company->id,
            'payroll_period_id' => $period->id,
            'salary_month' => '2026-05',
        ]);
        $this->assertDatabaseHas('wps_payroll_batch_items', [
            'employee_id' => $employee->id,
            'net_pay' => 11500,
            'bank_routing_code' => 'DEMOAEAD',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'wps_payroll_batch.generated',
        ]);
    }

    public function test_wps_export_rejects_missing_employee_bank_details(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        [$company, $user] = $this->payrollManager();
        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-NOBANK',
            'first_name' => 'No',
            'last_name' => 'Bank',
            'display_name' => 'No Bank',
            'status' => 'active',
            'basic_salary' => 9000,
        ]);
        $period = PayrollPeriod::query()->create([
            'company_id' => $company->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => 'approved',
        ]);
        Payslip::query()->create([
            'company_id' => $company->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'gross_pay' => 9000,
            'net_pay' => 9000,
            'status' => 'approved',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/payroll-periods/{$period->id}/wps-export")
            ->assertUnprocessable()
            ->assertJsonPath('errors.employees.0', 'EMP-NOBANK missing bank_iban, bank_routing_code, wps_person_id');
    }

    public function test_wps_batch_status_can_be_tracked_after_generation(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        [$company, $user] = $this->payrollManager();
        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-WPS2',
            'first_name' => 'Status',
            'last_name' => 'Employee',
            'display_name' => 'Status Employee',
            'status' => 'active',
            'basic_salary' => 7000,
            'bank_iban' => 'AE070331234567890999999',
            'bank_routing_code' => 'DEMOAEAD',
            'wps_person_id' => 'P999999',
        ]);
        $period = PayrollPeriod::query()->create([
            'company_id' => $company->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => 'approved',
        ]);
        Payslip::query()->create([
            'company_id' => $company->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'gross_pay' => 7000,
            'net_pay' => 7000,
            'status' => 'approved',
        ]);

        Sanctum::actingAs($user);

        $batchId = $this->postJson("/api/payroll-periods/{$period->id}/wps-export")
            ->assertCreated()
            ->json('data.wps_payroll_batch.id');

        $this->postJson("/api/wps-payroll-batches/{$batchId}/status", ['status' => 'submitted'])
            ->assertOk()
            ->assertJsonPath('data.wps_payroll_batch.status', 'submitted');

        $this->postJson("/api/wps-payroll-batches/{$batchId}/status", ['status' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('data.wps_payroll_batch.status', 'accepted');

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'wps_payroll_batch.status_updated',
        ]);
    }

    private function payrollManager(): array
    {
        $company = Company::query()->create([
            'name' => 'Demo Company',
            'mohre_establishment_number' => 'MOHRE-123',
            'wps_agent_code' => 'AGENT01',
            'wps_file_sender_id' => 'SENDER01',
        ]);
        $user = User::factory()->create();
        $role = Role::query()->where('slug', 'payroll_manager')->firstOrFail();

        $user->roles()->attach($role->id, [
            'company_id' => $company->id,
            'scope' => 'company',
        ]);

        return [$company, $user];
    }
}
