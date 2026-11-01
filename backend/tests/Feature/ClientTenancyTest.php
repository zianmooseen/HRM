<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CompanySubscription;
use App\Models\PlatformClient;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Tenancy\CreateTenantDatabase;
use App\Services\Tenancy\ProvisionClient;
use App\Services\Tenancy\TenantDatabaseManager;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ClientTenancyTest extends TestCase
{
    use RefreshDatabase;

    protected bool $legacySchema = false;

    private string $directory;

    private User $platformAdmin;

    private string $centralConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/hrm-clients-'.bin2hex(random_bytes(8));
        $this->centralConnection = DB::getDefaultConnection();
        // These tests inspect/copy SQLite tenant files, even when CI uses PostgreSQL centrally.
        config([
            'tenancy.sqlite_directory' => $this->directory,
            'tenancy.template_connection' => 'tenant_test_template',
            'database.connections.tenant_test_template' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        $this->seed(RoleAndPermissionSeeder::class);
        $this->platformAdmin = User::factory()->create(['status' => 'active']);
        $this->platformAdmin->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail()->id, ['scope' => 'global']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_adding_a_client_creates_an_isolated_database_and_no_demo_records(): void
    {
        Sanctum::actingAs($this->platformAdmin);
        $response = $this->postJson('/api/platform/clients', $this->payload('alpha'))
            ->assertCreated()->assertJsonPath('data.client.provisioning_status', 'ready');
        $client = PlatformClient::findOrFail($response->json('data.client.id'));
        $this->assertFileExists($this->directory.'/'.$client->database_name.'.sqlite');
        $this->assertNull($client->setup_payload);
        $this->assertStringNotContainsString('database_name', $response->getContent());
        $this->assertStringNotContainsString('admin_password', $response->getContent());
        $this->assertDatabaseCount('users', 1);
        $this->assertFalse(Schema::hasTable('branches'));
        $this->assertDatabaseHas('tenant_provisioning_runs', ['status' => 'completed', 'platform_client_id' => $client->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client.provisioned', 'company_id' => $client->company_id]);
        app(TenantDatabaseManager::class)->run($client, function () use ($client) {
            $this->assertDatabaseHas('companies', ['id' => $client->company_id, 'name' => 'ALPHA Company']);
            $this->assertDatabaseHas('branches', ['code' => 'MAIN']);
            $this->assertDatabaseCount('users', 1);
            $this->assertDatabaseCount('employees', 0);
            $this->assertDatabaseCount('payslips', 0);
            $this->assertDatabaseCount('company_compliance_settings', 1);
            $this->assertTrue(User::first()->hasRole('company_admin'));
            $this->assertTrue(Hash::check('Strong-test-password-123', User::first()->password));
            $this->assertFalse(Schema::hasTable('platform_clients'));
        });
        $this->assertSame($this->centralConnection, DB::getDefaultConnection());
    }

    public function test_clients_can_use_the_same_username_without_sharing_data_or_sessions(): void
    {
        $alpha = $this->client('alpha');
        $beta = $this->client('beta');
        $this->assertNotSame($alpha->database_name, $beta->database_name);
        app(TenantDatabaseManager::class)->run($alpha, fn () => Branch::first()->update(['name' => 'Alpha only']));
        app(TenantDatabaseManager::class)->run($beta, fn () => Branch::first()->update(['name' => 'Beta only']));
        $this->withHeader('Origin', 'http://localhost:3000');
        $this->postJson('/api/login', $this->login('alpha'))->assertOk()->assertJsonPath('data.user.client_id', $alpha->id);
        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.user.client_id', $alpha->id);
        $this->getJson('/api/branches')->assertOk()->assertJsonPath('data.branches.0.name', 'Alpha only');
        $this->getJson('/api/branches?client_code=beta')->assertOk()->assertJsonPath('data.branches.0.name', 'Alpha only');
        $this->getJson('/api/branches?scope_company_id='.$beta->company_id)->assertForbidden();
        $this->postJson('/api/login', $this->login('beta'))->assertOk()->assertJsonPath('data.user.client_id', $beta->id);
        $this->getJson('/api/branches')->assertOk()->assertJsonPath('data.branches.0.name', 'Beta only');
        $this->postJson('/api/logout')->assertOk();
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_client_users_cannot_access_platform_even_with_a_local_super_admin_role(): void
    {
        $client = $this->client('alpha');
        app(TenantDatabaseManager::class)->run($client, function () {
            User::first()->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail()->id, ['scope' => 'global']);
        });
        $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/login', $this->login('alpha'))->assertOk();
        $this->getJson('/api/platform/clients')->assertForbidden();
        $this->postJson('/api/platform/clients', $this->payload('beta'))->assertForbidden();
        $this->getJson('/api/platform/billing/invoices')->assertForbidden();
        $this->assertDatabaseCount('platform_clients', 1);
    }

    public function test_platform_support_writes_are_isolated_and_audited(): void
    {
        $alpha = $this->client('alpha');
        $beta = $this->client('beta');
        Sanctum::actingAs($this->platformAdmin);
        $this->postJson('/api/branches?scope_company_id='.$alpha->company_id, ['name' => 'Support branch', 'code' => 'SUP', 'status' => 'active'])->assertCreated();
        $this->getJson('/api/branches?scope_company_id='.$beta->company_id)->assertOk()->assertJsonCount(1, 'data.branches');
        $this->getJson('/api/branches?scope_company_id='.$alpha->company_id)->assertOk()->assertJsonCount(2, 'data.branches');
        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.user.id', $this->platformAdmin->id)->assertJsonPath('data.user.client_id', null);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client.support_access', 'company_id' => $alpha->company_id]);
        $this->assertFalse(Schema::hasTable('branches'));
        app(TenantDatabaseManager::class)->run($alpha, function () {
            $support = User::whereNotNull('platform_user_id')->firstOrFail();
            $this->assertSame('support', $support->status);
            $this->assertDatabaseHas('branches', ['code' => 'SUP', 'created_by' => $support->id]);
            $this->assertDatabaseHas('audit_logs', ['action' => 'branch.created', 'actor_user_id' => $support->id]);
        });
    }

    public function test_failed_setup_retries_the_same_database_without_duplicates(): void
    {
        $provisioner = Mockery::mock(ProvisionClient::class, [app(TenantDatabaseManager::class), app(CreateTenantDatabase::class)])->makePartial();
        $provisioner->shouldReceive('migrate')->once()->andThrow(new RuntimeException('Simulated migration failure'));
        $client = $provisioner->register($this->payload('alpha'), $this->platformAdmin);
        $this->assertSame('failed', $client->provisioning_status);
        $database = $client->database_name;
        $this->assertNotNull($client->setup_payload);
        $this->assertStringNotContainsString('Strong-test-password', $client->getRawOriginal('setup_payload'));
        $this->assertDatabaseHas('tenant_provisioning_runs', ['status' => 'failed', 'step' => 'migrations']);
        $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/login', $this->login('alpha'))->assertUnprocessable();
        Sanctum::actingAs($this->platformAdmin);
        $this->postJson('/api/platform/clients/'.$client->id.'/retry')->assertOk()->assertJsonPath('data.client.provisioning_status', 'ready');
        $this->postJson('/api/platform/clients/'.$client->id.'/retry')->assertOk();
        $this->assertSame($database, $client->refresh()->database_name);
        $this->assertDatabaseCount('tenant_provisioning_runs', 2);
        $this->assertCount(1, glob($this->directory.'/*.sqlite'));
        app(TenantDatabaseManager::class)->run($client, function () {
            $this->assertDatabaseCount('users', 1);
            $this->assertDatabaseCount('branches', 1);
        });
    }

    public function test_invalid_and_duplicate_client_codes_do_not_create_databases(): void
    {
        Sanctum::actingAs($this->platformAdmin);
        $this->postJson('/api/platform/clients', $this->payload('../bad'))->assertUnprocessable();
        $this->postJson('/api/platform/clients', $this->payload('alpha'))->assertCreated();
        $this->postJson('/api/platform/clients', $this->payload('alpha'))->assertUnprocessable();
        $this->assertDatabaseCount('platform_clients', 1);
        $this->assertCount(1, glob($this->directory.'/*.sqlite'));
    }

    public function test_failure_restores_connection_and_wrong_database_identity_fails_closed(): void
    {
        $client = $this->client('alpha');
        $manager = app(TenantDatabaseManager::class);
        try {
            $manager->run($client, function () {
                throw new RuntimeException('test');
            });
            $this->fail('Expected callback failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('test', $exception->getMessage());
        }
        $this->assertSame($this->centralConnection, DB::getDefaultConnection());
        $this->assertNull($manager->current());
        $manager->run($client, fn () => DB::table('tenant_identity')->update(['uuid' => 'unrelated-client']));
        try {
            $manager->run($client, fn () => $this->fail('Must not reach another client database.'));
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('identity', $exception->getMessage());
        }
        $this->assertSame($this->centralConnection, DB::getDefaultConnection());
        $this->assertNull($manager->current());
    }

    public function test_organization_options_and_billing_use_the_correct_database(): void
    {
        $alpha = $this->client('alpha');
        $beta = $this->client('beta');
        $plan = SubscriptionPlan::create(['name' => 'Growth', 'code' => 'growth']);
        CompanySubscription::create(['company_id' => $alpha->company_id, 'subscription_plan_id' => $plan->id, 'status' => 'active', 'starts_on' => today()]);
        Sanctum::actingAs($this->platformAdmin);
        $this->getJson('/api/organization-scope')->assertOk()->assertJsonCount(2, 'data.companies')->assertJsonCount(1, 'data.companies.0.branches');
        $this->getJson('/api/billing/current?scope_company_id='.$alpha->company_id)->assertOk()->assertJsonPath('data.company_subscription.plan.code', 'growth');
        $this->getJson('/api/billing/current?scope_company_id='.$beta->company_id)->assertOk()->assertJsonPath('data.company_subscription', null);
        Auth::forgetGuards();
        $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/login', $this->login('alpha'))->assertOk();
        $this->getJson('/api/billing/current')->assertOk()->assertJsonPath('data.company_subscription.plan.code', 'growth');
        $this->getJson('/api/organization-scope')->assertOk()->assertJsonCount(1, 'data.companies')->assertJsonPath('data.companies.0.id', $alpha->company_id);
    }

    public function test_restoring_one_client_leaves_another_unchanged_and_revokes_old_sessions(): void
    {
        $alpha = $this->client('alpha');
        $beta = $this->client('beta');
        $manager = app(TenantDatabaseManager::class);
        $path = $manager->configuration($alpha)['database'];
        $backup = $this->directory.'/alpha.backup';
        copy($path, $backup);
        $manager->run($alpha, fn () => Branch::first()->update(['name' => 'Change after backup']));
        $manager->run($beta, fn () => Branch::first()->update(['name' => 'Beta preserved']));
        $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/login', $this->login('alpha'))->assertOk();
        $this->artisan('clients:maintenance', ['client' => 'alpha'])->assertSuccessful();
        $this->getJson('/api/me')->assertUnauthorized();
        $this->postJson('/api/login', $this->login('alpha'))->assertUnprocessable();
        copy($backup, $path);
        $this->artisan('clients:migrate', ['--client' => 'alpha', '--force' => true])->assertSuccessful();
        $this->artisan('clients:maintenance', ['client' => 'alpha', '--resume' => true])->assertSuccessful();
        $this->getJson('/api/me')->assertUnauthorized();
        $this->postJson('/api/login', $this->login('alpha'))->assertOk();
        $this->getJson('/api/branches')->assertOk()->assertJsonPath('data.branches.0.name', 'Main branch');
        $this->postJson('/api/login', $this->login('beta'))->assertOk();
        $this->getJson('/api/branches')->assertOk()->assertJsonPath('data.branches.0.name', 'Beta preserved');
    }

    public function test_rate_limit_keys_include_the_client_identity(): void
    {
        $alpha = $this->client('alpha');
        $beta = $this->client('beta');
        $manager = app(TenantDatabaseManager::class);
        $request = Request::create('/api/branches');
        $request->setUserResolver(fn () => (object) ['id' => 1]);
        $limiter = RateLimiter::limiter('api');
        $alphaKey = $manager->run($alpha, fn () => $limiter($request)->key);
        $betaKey = $manager->run($beta, fn () => $limiter($request)->key);
        $this->assertNotSame($alphaKey, $betaKey);
        $this->assertNotSame($alphaKey, $limiter($request)->key);
    }

    public function test_client_upgrade_preserves_preexisting_maintenance_and_requires_application_maintenance(): void
    {
        $alpha = $this->client('alpha');
        $beta = $this->client('beta');
        $beta->update(['maintenance_at' => now()]);
        $this->artisan('clients:upgrade', ['--force' => true])->assertFailed();
        $this->mock(\Illuminate\Contracts\Foundation\MaintenanceMode::class, function ($mock) {
            $mock->shouldReceive('active')->andReturn(true);
        });
        $this->artisan('clients:upgrade', ['--force' => true])->assertSuccessful();
        $this->assertNull($alpha->refresh()->maintenance_at);
        $this->assertNotNull($beta->refresh()->maintenance_at);
        $this->assertSame(2, $alpha->session_version);
    }

    public function test_login_without_client_code_uses_the_account_whose_password_matches(): void
    {
        $alpha = $this->client('alpha');
        $beta = $this->client('beta');
        app(TenantDatabaseManager::class)->run($beta, fn () => User::first()->update(['password' => 'Different-beta-password-123']));
        $this->withHeader('Origin', 'http://localhost:3000');
        $this->postJson('/api/login', ['login' => 'client.admin', 'password' => 'Strong-test-password-123'])
            ->assertOk()->assertJsonPath('data.user.client_id', $alpha->id);
        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.user.client_id', $alpha->id);
        $this->postJson('/api/login', ['email' => 'admin@example.test', 'password' => 'Different-beta-password-123'])
            ->assertOk()->assertJsonPath('data.user.client_id', $beta->id);
        $this->getJson('/api/branches?organization_id='.$alpha->uuid)->assertOk()->assertJsonCount(1, 'data.branches');
        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.user.client_id', $beta->id);
    }

    public function test_shared_credentials_require_company_selection_after_password_verification(): void
    {
        $alpha = $this->client('alpha');
        $beta = $this->client('beta');
        $this->withHeader('Origin', 'http://localhost:3000');
        $credentials = ['login' => 'client.admin', 'password' => 'Strong-test-password-123'];
        $this->postJson('/api/login', ['login' => 'client.admin', 'password' => 'wrong'])
            ->assertUnprocessable()->assertJsonMissingPath('data.organization_options');
        $response = $this->postJson('/api/login', $credentials)->assertOk()->assertJsonCount(2, 'data.organization_options')
            ->assertJsonMissingPath('data.user');
        $this->assertEqualsCanonicalizing([$alpha->uuid, $beta->uuid], array_column($response->json('data.organization_options'), 'id'));
        $this->assertStringNotContainsString($alpha->database_name, $response->getContent());
        $this->getJson('/api/me')->assertUnauthorized();
        $this->postJson('/api/login', $credentials + ['organization_id' => (string) \Illuminate\Support\Str::uuid()])->assertUnprocessable();
        $this->postJson('/api/login', $credentials + ['organization_id' => 'not-a-uuid'])->assertUnprocessable();
        $this->postJson('/api/login', $credentials + ['organization_id' => $beta->uuid])
            ->assertOk()->assertJsonPath('data.user.client_id', $beta->id);
        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.user.client_id', $beta->id);
        $this->getJson('/api/platform/clients')->assertForbidden();
    }

    public function test_account_edits_update_lookup_and_stale_rows_never_authenticate(): void
    {
        $client = $this->client('alpha');
        $manager = app(TenantDatabaseManager::class);
        $manager->run($client, fn () => User::first()->update(['username' => 'changed.admin', 'email' => 'changed@example.test']));
        $this->withHeader('Origin', 'http://localhost:3000');
        $this->postJson('/api/login', ['login' => 'client.admin', 'password' => 'Strong-test-password-123'])->assertUnprocessable();
        $this->postJson('/api/login', ['login' => 'changed.admin', 'password' => 'Strong-test-password-123'])->assertOk();
        $this->postJson('/api/logout')->assertOk();
        $manager->run($client, fn () => User::first()->update(['status' => 'suspended']));
        $this->assertDatabaseCount('client_login_identifiers', 0);
        DB::table('client_login_identifiers')->insert([
            'platform_client_id' => $client->id, 'user_id' => 1,
            'identifier_hash' => app(\App\Services\Tenancy\ClientLoginDirectory::class)->fingerprint('changed.admin'),
        ]);
        $this->postJson('/api/login', ['login' => 'changed.admin', 'password' => 'Strong-test-password-123'])->assertUnprocessable();
        $this->getJson('/api/me')->assertUnauthorized();
        $manager->run($client, fn () => User::first()->delete());
        $this->assertDatabaseCount('client_login_identifiers', 0);
    }

    public function test_reindex_restores_lookup_without_storing_identifiers_or_passwords(): void
    {
        $client = $this->client('alpha');
        DB::table('client_login_identifiers')->delete();
        $this->artisan('clients:sync-logins', ['--client' => 'alpha'])->assertSuccessful();
        $this->assertDatabaseCount('client_login_identifiers', 2);
        $this->assertEqualsCanonicalizing(['id', 'platform_client_id', 'user_id', 'identifier_hash'], Schema::getColumnListing('client_login_identifiers'));
        $this->assertSame(64, strlen(DB::table('client_login_identifiers')->value('identifier_hash')));
        $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/login', ['login' => 'client.admin', 'password' => 'Strong-test-password-123'])
            ->assertOk()->assertJsonPath('data.user.client_id', $client->id);
    }

    public function test_paused_clients_and_support_accounts_are_not_discovered(): void
    {
        $client = $this->client('alpha');
        $client->update(['maintenance_at' => now()]);
        $this->withHeader('Origin', 'http://localhost:3000');
        $credentials = ['login' => 'client.admin', 'password' => 'Strong-test-password-123'];
        $this->postJson('/api/login', $credentials)->assertUnprocessable();
        $client->update(['maintenance_at' => null]);
        app(TenantDatabaseManager::class)->run($client, fn () => User::first()->forceFill(['platform_user_id' => $this->platformAdmin->id])->save());
        $this->assertDatabaseCount('client_login_identifiers', 0);
        $this->postJson('/api/login', $credentials)->assertUnprocessable();
    }

    public function test_platform_and_client_credentials_are_never_silently_confused(): void
    {
        $client = $this->client('alpha');
        $this->platformAdmin->update(['username' => 'client.admin', 'password' => 'Strong-test-password-123']);
        $this->withHeader('Origin', 'http://localhost:3000');
        $credentials = ['login' => 'client.admin', 'password' => 'Strong-test-password-123'];
        $this->postJson('/api/login', $credentials)->assertOk()->assertJsonCount(2, 'data.organization_options');
        $this->postJson('/api/login', $credentials + ['organization_id' => 'platform'])
            ->assertOk()->assertJsonPath('data.user.client_id', null);
        $this->getJson('/api/platform/clients')->assertOk();
        $this->postJson('/api/login', $credentials + ['organization_id' => $client->uuid])
            ->assertOk()->assertJsonPath('data.user.client_id', $client->id);
        $this->getJson('/api/platform/clients')->assertForbidden();
    }

    public function test_discovery_is_rate_limited_before_password_matching(): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->postJson('/api/login', ['login' => 'missing.user', 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/api/login', ['login' => 'missing.user', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_discovery_never_falls_back_when_an_indexed_client_has_the_wrong_database_identity(): void
    {
        $this->client('alpha');
        $beta = $this->client('beta');
        app(TenantDatabaseManager::class)->run($beta, fn () => DB::table('tenant_identity')->update(['uuid' => 'unrelated-client']));
        $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/login', ['login' => 'client.admin', 'password' => 'Strong-test-password-123'])
            ->assertStatus(503)->assertJsonMissingPath('data.user');
        $this->assertSame($this->centralConnection, DB::getDefaultConnection());
        $this->getJson('/api/me')->assertUnauthorized();
    }

    private function client(string $code): PlatformClient
    {
        $client = app(ProvisionClient::class)->register($this->payload($code), $this->platformAdmin);
        $this->assertSame('ready', $client->provisioning_status, $client->runs()->latest('id')->first()?->error_message ?? '');

        return $client;
    }

    private function login(string $code): array
    {
        return ['client_code' => $code, 'login' => 'client.admin', 'password' => 'Strong-test-password-123'];
    }

    private function payload(string $code): array
    {
        return ['client_code' => $code, 'company_name' => strtoupper($code).' Company', 'admin_name' => 'Client Admin', 'admin_username' => 'client.admin', 'admin_email' => 'admin@example.test', 'admin_password' => 'Strong-test-password-123'];
    }
}
