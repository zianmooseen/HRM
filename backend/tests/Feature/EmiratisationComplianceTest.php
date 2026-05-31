<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\LegalRuleSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmiratisationComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_large_company_emiratisation_status_can_be_calculated_and_snapshotted(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class]);

        [$company, $user] = $this->companyAdmin([
            'emiratisation_applicable' => true,
            'emiratisation_category' => 'large_50_plus',
        ]);
        $this->employees($company, 99, ['is_skilled_worker' => true]);
        $this->employees($company, 1, ['is_skilled_worker' => true, 'is_uae_citizen' => true]);

        Sanctum::actingAs($user);

        $this->getJson('/api/compliance/emiratisation')
            ->assertOk()
            ->assertJsonPath('data.snapshot.total_active_workers', 100)
            ->assertJsonPath('data.snapshot.total_skilled_workers', 100)
            ->assertJsonPath('data.snapshot.total_skilled_uae_citizens', 1)
            ->assertJsonPath('data.snapshot.required_uae_citizens', 2)
            ->assertJsonPath('data.snapshot.missing_uae_citizens', 1)
            ->assertJsonPath('data.snapshot.estimated_contribution_amount', 96000)
            ->assertJsonPath('data.snapshot.compliance_status', 'non_compliant');

        $this->postJson('/api/compliance/emiratisation/snapshot')
            ->assertCreated()
            ->assertJsonPath('data.snapshot.required_uae_citizens', 2)
            ->assertJsonPath('data.snapshot.missing_uae_citizens', 1);

        $this->assertDatabaseHas('emiratisation_snapshots', [
            'company_id' => $company->id,
            'total_active_workers' => 100,
            'required_uae_citizens' => 2,
            'missing_uae_citizens' => 1,
            'compliance_status' => 'non_compliant',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'emiratisation_snapshot.created',
        ]);
    }

    public function test_emiratisation_is_not_applicable_when_company_is_not_marked_applicable(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class]);

        [$company, $user] = $this->companyAdmin();
        $this->employees($company, 5, ['is_skilled_worker' => true]);

        Sanctum::actingAs($user);

        $this->getJson('/api/compliance/emiratisation')
            ->assertOk()
            ->assertJsonPath('data.snapshot.total_active_workers', 5)
            ->assertJsonPath('data.snapshot.required_uae_citizens', 0)
            ->assertJsonPath('data.snapshot.compliance_status', 'not_applicable');
    }

    private function companyAdmin(array $overrides = []): array
    {
        $company = Company::query()->create([
            'name' => 'Demo Company',
            'default_currency' => 'AED',
            ...$overrides,
        ]);
        $user = User::factory()->create();
        $role = Role::query()->where('slug', 'company_admin')->firstOrFail();

        $user->roles()->attach($role->id, [
            'company_id' => $company->id,
            'scope' => 'company',
        ]);

        return [$company, $user];
    }

    private function employees(Company $company, int $count, array $overrides = []): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Employee::query()->create([
                'company_id' => $company->id,
                'employee_code' => 'EMR-'.$company->id.'-'.$i.'-'.count($overrides),
                'first_name' => 'Worker',
                'last_name' => (string) $i,
                'display_name' => 'Worker '.$i,
                'status' => 'active',
                ...$overrides,
            ]);
        }
    }
}
