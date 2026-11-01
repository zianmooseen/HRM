<?php

namespace Tests\Feature;

use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Database\PostgresConnection;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Mockery;
use Tests\TestCase;

class PreparePostgresProvisionerTest extends TestCase
{
    protected bool $legacySchema = false;

    public function test_setup_requires_explicit_force_and_maintenance(): void
    {
        Process::fake();
        $this->mock(MaintenanceMode::class)->shouldReceive('active')->andReturn(false);
        $this->artisan('clients:prepare-provisioner')->assertFailed();
        $this->artisan('clients:prepare-provisioner', ['--force' => true])->assertFailed();
        Process::assertNothingRan();
    }

    public function test_setup_grants_only_createdb_to_the_configured_role_and_verifies_it(): void
    {
        Process::fake();
        $this->mock(MaintenanceMode::class)->shouldReceive('active')->andReturn(true);
        $connection = Mockery::mock(PostgresConnection::class);
        DB::shouldReceive('connection')->with(config('tenancy.provisioning_connection'))->andReturn($connection);
        $connection->shouldReceive('getDriverName')->andReturn('pgsql');
        $connection->shouldReceive('getConfig')->with('host')->andReturn('127.0.0.1');
        $connection->shouldReceive('getConfig')->with('port')->andReturn(5432);
        $connection->shouldReceive('selectOne')->twice()->andReturn(
            (object) ['name' => 'hrm"role', 'rolcreatedb' => false, 'rolsuper' => false],
            (object) ['rolcreatedb' => true, 'rolsuper' => false],
        );
        $this->artisan('clients:prepare-provisioner', ['--force' => true])->assertSuccessful();
        Process::assertRan(fn (PendingProcess $process) => $process->command === [
            'sudo', '-n', '-u', 'postgres', 'psql', '--no-psqlrc', '--port', '5432', '--dbname', 'postgres',
            '--set', 'ON_ERROR_STOP=1', '--command', 'ALTER ROLE "hrm""role" CREATEDB',
        ]);
    }

    public function test_existing_provisioner_needs_no_server_administrator_access(): void
    {
        Process::fake();
        $this->mock(MaintenanceMode::class)->shouldReceive('active')->andReturn(true);
        $connection = Mockery::mock(PostgresConnection::class);
        DB::shouldReceive('connection')->with(config('tenancy.provisioning_connection'))->andReturn($connection);
        $connection->shouldReceive('getDriverName')->andReturn('pgsql');
        $connection->shouldReceive('selectOne')->once()->andReturn((object) ['rolcreatedb' => true, 'rolsuper' => false]);
        $this->artisan('clients:prepare-provisioner', ['--force' => true])->assertSuccessful();
        Process::assertNothingRan();
    }

    public function test_remote_database_does_not_change_a_local_postgres_role(): void
    {
        Process::fake();
        $this->mock(MaintenanceMode::class)->shouldReceive('active')->andReturn(true);
        $connection = Mockery::mock(PostgresConnection::class);
        DB::shouldReceive('connection')->with(config('tenancy.provisioning_connection'))->andReturn($connection);
        $connection->shouldReceive('getDriverName')->andReturn('pgsql');
        $connection->shouldReceive('selectOne')->once()->andReturn((object) ['name' => 'hrm', 'rolcreatedb' => false, 'rolsuper' => false]);
        $connection->shouldReceive('getConfig')->with('host')->andReturn('db.example.test');
        $this->artisan('clients:prepare-provisioner', ['--force' => true])
            ->expectsOutputToContain('Remote PostgreSQL must be configured by its database administrator')->assertFailed();
        Process::assertNothingRan();
    }
}
