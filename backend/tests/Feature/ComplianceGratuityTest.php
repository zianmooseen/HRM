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

class ComplianceGratuityTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_calculate_uae_gratuity_for_employee(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class]);

        [$company, $user] = $this->companyAdmin();
        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => 'GRAT-001',
            'first_name' => 'Omar',
            'last_name' => 'Said',
            'display_name' => 'Omar Said',
            'status' => 'active',
            'hire_date' => '2020-01-01',
            'basic_salary' => 9000,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/compliance/gratuity', [
            'employee_id' => $employee->id,
            'termination_date' => '2026-01-01',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.gratuity.daily_wage', 300)
            ->assertJsonPath('data.gratuity.gratuity_days', 135.25)
            ->assertJsonPath('data.gratuity.gratuity_amount', 40573.97);
    }

    public function test_gratuity_is_zero_before_one_year_of_service(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class]);

        [$company, $user] = $this->companyAdmin();
        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => 'GRAT-002',
            'first_name' => 'Mona',
            'last_name' => 'Ali',
            'display_name' => 'Mona Ali',
            'status' => 'active',
            'hire_date' => '2026-01-01',
            'basic_salary' => 9000,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/compliance/gratuity', [
            'employee_id' => $employee->id,
            'termination_date' => '2026-06-01',
        ])->assertOk()
            ->assertJsonPath('data.gratuity.gratuity_days', 0)
            ->assertJsonPath('data.gratuity.gratuity_amount', 0);
    }

    public function test_gratuity_rejects_foreign_company_employee(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LegalRuleSeeder::class]);

        [, $user] = $this->companyAdmin();
        $otherCompany = Company::query()->create(['name' => 'Other Company']);
        $foreignEmployee = Employee::query()->create([
            'company_id' => $otherCompany->id,
            'employee_code' => 'GRAT-003',
            'first_name' => 'Foreign',
            'last_name' => 'Employee',
            'display_name' => 'Foreign Employee',
            'status' => 'active',
            'hire_date' => '2020-01-01',
            'basic_salary' => 9000,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/compliance/gratuity', [
            'employee_id' => $foreignEmployee->id,
            'termination_date' => '2026-01-01',
        ])->assertUnprocessable();
    }

    private function companyAdmin(): array
    {
        $company = Company::query()->create(['name' => 'Demo Company', 'default_currency' => 'AED']);
        $user = User::factory()->create();
        $role = Role::query()->where('slug', 'company_admin')->firstOrFail();

        $user->roles()->attach($role->id, [
            'company_id' => $company->id,
            'scope' => 'company',
        ]);

        return [$company, $user];
    }
}
