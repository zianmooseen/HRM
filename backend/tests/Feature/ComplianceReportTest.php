<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyComplianceSetting;
use App\Models\EmiratisationSnapshot;
use App\Models\LegalRuleSet;
use App\Models\PublicHoliday;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\LegalRuleSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ComplianceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_view_compliance_report_summary(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class]);

        [$company, $user] = $this->companyAdmin();
        $this->complianceSettings($company);
        PublicHoliday::query()->create([
            'company_id' => $company->id,
            'name' => 'UAE National Day',
            'holiday_date' => '2026-12-02',
            'country_code' => 'AE',
            'paid' => true,
            'source' => 'government',
            'status' => 'active',
        ]);
        EmiratisationSnapshot::query()->create([
            'company_id' => $company->id,
            'snapshot_date' => '2026-05-31',
            'total_active_workers' => 100,
            'total_skilled_workers' => 100,
            'total_active_uae_citizens' => 2,
            'total_skilled_uae_citizens' => 2,
            'required_uae_citizens' => 2,
            'missing_uae_citizens' => 0,
            'estimated_contribution_amount' => 0,
            'compliance_status' => 'compliant',
        ]);
        AuditLog::query()->create([
            'company_id' => $company->id,
            'actor_user_id' => $user->id,
            'action' => 'company_compliance_settings.updated',
            'auditable_type' => CompanyComplianceSetting::class,
            'auditable_id' => 1,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/compliance/reports')
            ->assertOk()
            ->assertJsonPath('data.summary.company.name', 'Demo Company')
            ->assertJsonPath('data.summary.settings.payroll_day_divisor', 'calendar_30')
            ->assertJsonPath('data.summary.public_holiday_count', 1)
            ->assertJsonPath('data.summary.latest_emiratisation_snapshot.compliance_status', 'compliant')
            ->assertJsonPath('data.summary.recent_audit_logs.0.action', 'company_compliance_settings.updated')
            ->assertJsonPath('data.summary.exports.0', 'settings');
    }

    public function test_company_admin_can_export_compliance_csvs(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class]);

        [$company, $user] = $this->companyAdmin();
        $this->complianceSettings($company);
        PublicHoliday::query()->create([
            'company_id' => $company->id,
            'name' => 'New Year',
            'holiday_date' => '2026-01-01',
            'country_code' => 'AE',
            'paid' => true,
            'source' => 'government',
            'status' => 'active',
        ]);
        PublicHoliday::query()->create([
            'company_id' => Company::query()->create(['name' => 'Other Company'])->id,
            'name' => 'Foreign Holiday',
            'holiday_date' => '2026-01-02',
            'country_code' => 'AE',
            'paid' => true,
            'source' => 'company',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $response = $this->get('/api/compliance/reports/export?type=public_holidays')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('date,name,country_code,emirate,paid,source,status', $csv);
        $this->assertStringContainsString('2026-01-01,"New Year",AE,All,yes,government,active', $csv);
        $this->assertStringNotContainsString('Foreign Holiday', $csv);
    }

    public function test_compliance_report_rejects_unknown_export_type(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class]);

        [, $user] = $this->companyAdmin();

        Sanctum::actingAs($user);

        $this->get('/api/compliance/reports/export?type=unknown')
            ->assertUnprocessable();
    }

    private function companyAdmin(): array
    {
        $company = Company::query()->create([
            'name' => 'Demo Company',
            'default_currency' => 'AED',
            'emiratisation_applicable' => true,
            'emiratisation_category' => 'large_50_plus',
        ]);
        $user = User::factory()->create();
        $role = Role::query()->where('slug', 'company_admin')->firstOrFail();

        $user->roles()->attach($role->id, [
            'company_id' => $company->id,
            'scope' => 'company',
        ]);

        return [$company, $user];
    }

    private function complianceSettings(Company $company): CompanyComplianceSetting
    {
        return CompanyComplianceSetting::query()->create([
            'company_id' => $company->id,
            'legal_rule_set_id' => LegalRuleSet::query()->firstOrFail()->id,
            'payroll_day_divisor' => 'calendar_30',
            'annual_leave_accrual_method' => 'monthly',
            'annual_leave_carry_forward_allowed' => true,
            'annual_leave_max_carry_forward_days' => 15,
            'public_holidays_count_as_annual_leave' => false,
            'sick_leave_requires_medical_certificate' => true,
            'sick_leave_notification_days' => 3,
            'emiratisation_monitoring_enabled' => true,
        ]);
    }
}
