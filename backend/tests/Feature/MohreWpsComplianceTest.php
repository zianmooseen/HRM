<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Models\WpsPayrollBatch;
use App\Models\WpsProvider;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\WpsProviderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MohreWpsComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_configure_establishment_and_wps_provider(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, WpsProviderSeeder::class]);
        [$company, $admin] = $this->companyAdmin();
        Sanctum::actingAs($admin);

        $establishmentId = $this->postJson('/api/mohre-establishments', [
            'establishment_name' => 'Dubai Main Establishment',
            'mohre_establishment_number' => 'EST-10001',
            'emirate' => 'Dubai',
            'status' => 'active',
            'wps_required' => true,
        ])->assertCreated()
            ->assertJsonPath('data.mohre_establishment.company_id', $company->id)
            ->json('data.mohre_establishment.id');

        $provider = WpsProvider::query()->where('code', 'generic')->firstOrFail();

        $this->postJson('/api/wps-settings', [
            'mohre_establishment_id' => $establishmentId,
            'wps_provider_id' => $provider->id,
            'payroll_due_day' => 5,
            'salary_period_type' => 'monthly',
            'payment_currency' => 'AED',
            'sif_export_enabled' => true,
            'auto_mark_paid_allowed' => false,
            'agent_code' => 'AGENT-100',
            'sender_id' => 'SENDER-100',
            'status' => 'active',
        ])->assertOk()
            ->assertJsonPath('data.wps_setting.payroll_due_day', 5)
            ->assertJsonPath('data.wps_setting.provider.code', 'generic');

        $this->assertDatabaseHas('company_wps_settings', [
            'company_id' => $company->id,
            'mohre_establishment_id' => $establishmentId,
            'wps_provider_id' => $provider->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'company_wps_setting.updated',
        ]);
    }

    public function test_employee_government_identifiers_are_tenant_scoped_and_encrypted(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        [$company, $admin] = $this->companyAdmin();
        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => 'EMP-GOV-1',
            'first_name' => 'Government',
            'last_name' => 'Profile',
            'display_name' => 'Government Profile',
            'status' => 'active',
        ]);
        Sanctum::actingAs($admin);

        $this->putJson("/api/employees/{$employee->id}/government-profile", [
            'labour_card_number' => 'LC-SECRET-100',
            'work_permit_number' => 'WP-SECRET-100',
            'person_code' => 'PERSON-SECRET-100',
            'emirates_id_number' => '784-1990-1234567-1',
            'visa_file_number' => 'VISA-SECRET-100',
            'passport_number' => 'P-SECRET-100',
            'wps_employee_identifier' => 'WPS-SECRET-100',
        ])->assertOk()
            ->assertJsonPath('data.employee_government_profile.labour_card_number', 'LC-SECRET-100');

        $this->getJson("/api/employees/{$employee->id}")
            ->assertOk()
            ->assertJsonPath('data.employee.work_permit_number', 'WP-SECRET-100')
            ->assertJsonPath('data.employee.labor_card_number', 'LC-SECRET-100')
            ->assertJsonPath('data.employee.government_profile.person_code', 'PERSON-SECRET-100')
            ->assertJsonPath('data.employee.government_profile.emirates_id_number', '784-1990-1234567-1')
            ->assertJsonPath('data.employee.government_profile.visa_file_number', 'VISA-SECRET-100')
            ->assertJsonPath('data.employee.government_profile.passport_number', 'P-SECRET-100')
            ->assertJsonPath('data.employee.government_profile.wps_employee_identifier', 'WPS-SECRET-100');

        $raw = DB::table('employee_government_profiles')->where('employee_id', $employee->id)->first();
        $this->assertNotSame('LC-SECRET-100', $raw->labour_card_number);
        $this->assertNotSame('784-1990-1234567-1', $raw->emirates_id_number);

        [$otherCompany, $otherAdmin] = $this->companyAdmin('Other Company');
        Sanctum::actingAs($otherAdmin);

        $this->getJson("/api/employees/{$employee->id}/government-profile")->assertForbidden();
        $this->assertNotSame($company->id, $otherCompany->id);
    }

    public function test_transfer_proof_can_be_uploaded_and_verified(): void
    {
        Storage::fake('local');
        $this->seed(RoleAndPermissionSeeder::class);
        [$company, $admin] = $this->companyAdmin();
        $period = PayrollPeriod::query()->create([
            'company_id' => $company->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'payroll_due_date' => '2026-06-05',
            'status' => 'approved',
            'wps_status' => 'submitted_to_provider',
        ]);
        $batch = WpsPayrollBatch::query()->create([
            'company_id' => $company->id,
            'payroll_period_id' => $period->id,
            'batch_number' => 'WPS-PROOF-1',
            'status' => 'submitted',
            'file_format' => 'sif',
            'provider' => 'generic',
            'salary_month' => '2026-05',
            'record_count' => 1,
            'total_amount' => 1000,
            'proof_status' => 'missing',
        ]);
        Sanctum::actingAs($admin);

        $proofId = $this->post("/api/wps-payroll-batches/{$batch->id}/proofs", [
            'proof_type' => 'bank_confirmation',
            'provider_reference' => 'BANK-REF-100',
            'file' => UploadedFile::fake()->create('confirmation.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.wps_transfer_proof.status', 'uploaded')
            ->json('data.wps_transfer_proof.id');

        $this->postJson("/api/wps-transfer-proofs/{$proofId}/verify", ['status' => 'verified'])
            ->assertOk()
            ->assertJsonPath('data.wps_transfer_proof.status', 'verified');

        $this->assertDatabaseHas('wps_payroll_batches', [
            'id' => $batch->id,
            'proof_status' => 'verified',
        ]);
    }

    public function test_invalid_status_transition_and_reasonless_override_are_rejected(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        [$company, $admin] = $this->companyAdmin();
        $period = PayrollPeriod::query()->create([
            'company_id' => $company->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => 'approved',
        ]);
        $batch = WpsPayrollBatch::query()->create([
            'company_id' => $company->id,
            'payroll_period_id' => $period->id,
            'batch_number' => 'WPS-TRANSITION-1',
            'status' => 'generated',
            'file_format' => 'sif',
            'provider' => 'generic',
            'salary_month' => '2026-05',
            'record_count' => 1,
            'total_amount' => 1000,
        ]);
        Sanctum::actingAs($admin);

        $this->postJson("/api/wps-payroll-batches/{$batch->id}/status", ['status' => 'paid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $batch->update(['status' => 'needs_review']);
        $this->postJson("/api/wps-payroll-batches/{$batch->id}/status", ['status' => 'manual_override'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('manual_override_reason');
    }

    private function companyAdmin(string $companyName = 'WPS Company'): array
    {
        $company = Company::query()->create(['name' => $companyName]);
        $user = User::factory()->create();
        $role = Role::query()->where('slug', 'company_admin')->firstOrFail();
        $user->roles()->attach($role->id, [
            'company_id' => $company->id,
            'scope' => 'company',
        ]);

        return [$company, $user];
    }
}
