<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PlatformClient;
use App\Models\Role;
use App\Models\User;
use App\Services\Tenancy\TenantDatabaseManager;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResetHostedTestDataTest extends TestCase
{
    protected bool $legacySchema = false;

    public function test_reset_requires_the_explicit_disposable_data_confirmation(): void
    {
        $this->artisan('test-data:reset')->expectsOutputToContain('Reset refused')->assertFailed();
        $this->artisan('test-data:reset', ['--force' => true, '--confirm' => 'yes'])->assertFailed();
    }

    public function test_reset_requires_maintenance_before_reading_or_changing_data(): void
    {
        $this->mock(MaintenanceMode::class)->shouldReceive('active')->andReturn(false);
        $this->artisan('test-data:reset', ['--force' => true, '--confirm' => 'RESET_DISPOSABLE_HOSTED_TEST_DATA'])
            ->expectsOutputToContain('Put the application into maintenance')->assertFailed();
    }

    public function test_postgres_reset_archives_legacy_data_and_rebuilds_three_separate_clients(): void
    {
        $originalName = DB::getDefaultConnection();
        $original = DB::connection($originalName);
        if ($original->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL integration test runs in deployment CI.');
        }
        $database = 'hrm_reset_test_'.bin2hex(random_bytes(8));
        $oldClientDatabase = 'hrm_client_'.str_replace('-', '', (string) Str::uuid());
        $directory = sys_get_temp_dir().'/'.$database;
        $configuration = $original->getConfig();
        unset($configuration['url'], $configuration['read'], $configuration['write'], $configuration['name']);
        $original->statement('CREATE DATABASE "'.$database.'"');
        $original->statement('CREATE DATABASE "'.$oldClientDatabase.'"');
        config([
            'database.connections.hosted_reset_test' => array_replace($configuration, ['database' => $database]),
            'database.connections.hosted_reset_old_client' => array_replace($configuration, ['database' => $oldClientDatabase]),
            'tenancy.central_connection' => 'hosted_reset_test',
            'tenancy.template_connection' => 'hosted_reset_test',
            'tenancy.provisioning_connection' => 'hosted_reset_test',
            'tenancy.hosted_test_reset_directory' => $directory,
        ]);
        DB::setDefaultConnection('hosted_reset_test');
        Schema::clearResolvedInstance('db.schema');
        $this->mock(MaintenanceMode::class)->shouldReceive('active')->andReturn(true);
        Storage::fake('local');
        try {
            $this->artisan('migrate', ['--force' => true])->assertSuccessful();
            $this->seed(RoleAndPermissionSeeder::class);
            $admin = User::factory()->create(['id' => 71, 'password' => 'preserved-admin-password']);
            $admin->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail(), ['scope' => 'global']);
            $oldHash = $admin->password;
            User::factory()->create(['id' => 95]);
            $company = Company::forceCreate(['id' => 90, 'name' => 'Legacy test company']);
            Schema::create('branches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained();
            });
            DB::table('branches')->insert(['id' => 5, 'company_id' => $company->id]);
            PlatformClient::forceCreate([
                'id' => 60, 'uuid' => (string) Str::uuid(), 'client_code' => 'old-test',
                'company_id' => $company->id, 'database_name' => $oldClientDatabase,
                'created_by' => $admin->id,
            ]);
            DB::connection('hosted_reset_old_client')->statement('CREATE TABLE recovery_marker (id integer)');
            DB::connection('hosted_reset_old_client')->table('recovery_marker')->insert(['id' => 42]);
            Storage::disk('local')->put('companies/90/old-document.pdf', 'old file');

            $this->app->instance('env', 'production');
            $this->artisan('test-data:reset', ['--force' => true, '--confirm' => 'RESET_DISPOSABLE_HOSTED_TEST_DATA'])->assertSuccessful();

            $files = File::files($directory);
            $this->assertCount(1, $files);
            $manifest = json_decode(File::get($files[0]->getPathname()), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('completed', $manifest['status']);
            $this->assertSame(0600, fileperms($files[0]->getPathname()) & 0777);
            $this->assertSame(0700, fileperms($directory) & 0777);
            $this->assertSame('unchanged', $manifest['platform_password']);
            $this->assertSame(40, strlen($manifest['test_client_password']));
            $this->assertSame($oldHash, User::findOrFail(71)->password);
            $this->assertDatabaseCount('users', 1);
            $this->assertFalse(Schema::hasTable('branches'));
            $this->assertSame(1, DB::table($manifest['archive_schema'].'.branches')->count());
            $this->assertSame(90, DB::table($manifest['archive_schema'].'.branches')->value('company_id'));
            $this->assertSame(42, DB::connection('hosted_reset_old_client')->table('recovery_marker')->value('id'));
            Storage::disk('local')->assertExists('companies/90/old-document.pdf');
            $this->assertFalse(DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_namespace n, LATERAL aclexplode(n.nspacl) a WHERE n.nspname = ? AND a.grantee = 0 AND a.privilege_type = 'USAGE') AS allowed", [$manifest['archive_schema']])->allowed);
            $clients = PlatformClient::all();
            $this->assertCount(3, $clients);
            $this->assertCount(3, $clients->pluck('database_name')->unique());
            $this->assertDatabaseCount('billing_invoices', 9);
            $this->assertTrue(DB::table('client_login_identifiers')->exists());
            foreach ($clients as $client) {
                $this->assertGreaterThan(60, $client->id);
                $this->assertGreaterThan(90, $client->company_id);
                $this->assertSame('ready', $client->provisioning_status);
                app(TenantDatabaseManager::class)->run($client, function () use ($manifest) {
                    $this->assertDatabaseCount('employees', 160);
                    $this->assertFalse(Schema::hasTable('billing_invoices'));
                    foreach (User::all() as $user) {
                        $this->assertTrue(Hash::check($manifest['test_client_password'], $user->password));
                    }
                });
            }
            $this->artisan('schema:preflight')->assertSuccessful();
            $this->assertGreaterThan(95, User::factory()->create()->id);
        } finally {
            $clientDatabases = Schema::hasTable('platform_clients') ? PlatformClient::pluck('database_name')->all() : [];
            DB::setDefaultConnection($originalName);
            Schema::clearResolvedInstance('db.schema');
            DB::purge('hosted_reset_test');
            DB::purge('hosted_reset_old_client');
            foreach (array_unique([$database, $oldClientDatabase, ...$clientDatabases]) as $name) {
                $original->statement('DROP DATABASE "'.$name.'" WITH (FORCE)');
            }
            File::deleteDirectory($directory);
        }
    }
}
