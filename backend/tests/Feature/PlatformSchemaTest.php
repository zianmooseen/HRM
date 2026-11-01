<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\Tenancy\SchemaLayout;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $legacySchema = false;

    public function test_default_migrations_and_seeding_only_install_platform_tables(): void
    {
        $this->seed(DatabaseSeeder::class);
        $tables = array_map(fn ($table) => Str::afterLast($table, '.'), Schema::getTableListing());
        $this->assertEqualsCanonicalizing(SchemaLayout::PLATFORM_TABLES, $tables);
        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseCount('platform_clients', 0);
        $this->assertFalse(Schema::hasTable('employees'));
        $this->assertTrue(DB::table('wps_providers')->exists());
    }

    public function test_platform_hr_requests_require_a_real_client_and_reports_skip_unregistered_companies(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail(), ['scope' => 'global']);
        $company = Company::create(['name' => 'Directory only']);
        Sanctum::actingAs($admin);
        $this->getJson('/api/employees')->assertUnprocessable();
        $this->getJson('/api/employees?scope_company_id='.$company->id)->assertUnprocessable();
        $this->getJson('/api/organization-scope')->assertOk()->assertJsonCount(0, 'data.companies');
        $this->getJson('/api/platform/wps-risk')->assertOk()->assertJsonPath('data.companies.0.risk_status', 'unavailable');
        $this->getJson('/api/platform/wps-providers')->assertOk();
        $this->getJson('/api/companies')->assertOk();
        $this->getJson('/api/companies?scope_company_id='.$company->id.'&scope_branch_id=999')->assertOk();
        $this->getJson('/api/billing/current?scope_company_id='.$company->id.'&scope_branch_id=999')->assertOk();
        $this->artisan('wps:monitor-deadlines')->assertSuccessful();
    }

    public function test_cleanup_refuses_to_delete_populated_hr_tables(): void
    {
        Schema::create('employees', fn (Blueprint $table) => $table->id());
        DB::table('employees')->insert(['id' => 1]);
        try {
            SchemaLayout::removeLegacyTables(platform: true);
            $this->fail('Populated HR data must block cleanup.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('employees still contains records', $exception->getMessage());
        }
        $this->assertDatabaseCount('employees', 1);
    }

    public function test_deployment_preflight_reports_legacy_data_without_changing_schema(): void
    {
        Schema::create('branches', fn (Blueprint $table) => $table->id());
        DB::table('branches')->insert(['id' => 1]);
        $this->artisan('schema:preflight')->expectsOutputToContain('branches still contains records')->assertFailed();
        $this->assertDatabaseCount('branches', 1);
        $this->assertTrue(Schema::hasTable('users'));
    }

    public function test_empty_legacy_tables_are_removed_without_touching_platform_records(): void
    {
        $company = Company::create(['name' => 'Keep directory']);
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
        });
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
        });
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained();
        });
        Schema::table('branches', function (Blueprint $table) {
            $table->foreignId('manager_employee_id')->nullable()->constrained('employees');
        });
        SchemaLayout::removeLegacyTables(platform: true);
        $this->assertFalse(Schema::hasTable('branches'));
        $this->assertFalse(Schema::hasTable('departments'));
        $this->assertFalse(Schema::hasTable('employees'));
        $this->assertDatabaseHas('companies', ['id' => $company->id]);
    }
}
