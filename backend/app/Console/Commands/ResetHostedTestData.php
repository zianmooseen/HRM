<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Tenancy\TenantDatabaseManager;
use Database\Seeders\IsolatedDemoClientsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class ResetHostedTestData extends Command
{
    protected $signature = 'test-data:reset {--confirm= : Must be RESET_DISPOSABLE_HOSTED_TEST_DATA} {--force : Explicitly allow a hosted test reset}';

    protected $description = 'Archive the PostgreSQL public schema and replace disposable hosted test data with isolated clients';

    public function handle(): int
    {
        if (! $this->option('force') || $this->option('confirm') !== 'RESET_DISPOSABLE_HOSTED_TEST_DATA') {
            $this->error('Reset refused. Supply --force --confirm=RESET_DISPOSABLE_HOSTED_TEST_DATA only for disposable test data.');

            return self::FAILURE;
        }
        $manifestPath = null;
        try {
            if (! app()->isDownForMaintenance()) {
                throw new RuntimeException('Put the application into maintenance and drain jobs/requests first.');
            }
            $connection = DB::connection(config('tenancy.central_connection'));
            if ($connection->getDriverName() !== 'pgsql' || $connection->transactionLevel() !== 0) {
                throw new RuntimeException('Hosted test reset requires PostgreSQL outside a transaction.');
            }
            if ($connection->selectOne('SELECT current_schema() AS name')->name !== 'public') {
                throw new RuntimeException('Hosted test reset requires the public schema as the current schema.');
            }
            if (DB::getDefaultConnection() !== config('tenancy.central_connection') || app(TenantDatabaseManager::class)->current()) {
                throw new RuntimeException('Reset must run on the central connection, outside any client context.');
            }
            $schema = $connection->getSchemaBuilder();
            if (! $schema->hasTable('users') || ! $schema->hasTable('companies')) {
                throw new RuntimeException('Expected an existing HRM platform database; reset refused.');
            }
            $provisioning = DB::connection(config('tenancy.provisioning_connection'));
            $runtime = DB::connection(config('tenancy.template_connection'))->getConfig();
            if ($provisioning->getDriverName() !== 'pgsql' || $runtime['driver'] !== 'pgsql') {
                throw new RuntimeException('Hosted test reset requires PostgreSQL provisioning and runtime connections.');
            }
            $creator = $provisioning->selectOne('SELECT rolcreatedb, rolsuper FROM pg_roles WHERE rolname = current_user');
            if (! $creator || (! $creator->rolcreatedb && ! $creator->rolsuper)) {
                throw new RuntimeException('The provisioning database role needs CREATEDB before reset can begin.');
            }
            if (! $provisioning->selectOne('SELECT pg_has_role(current_user, ?, \'MEMBER\') AS allowed', [$runtime['username']])->allowed) {
                throw new RuntimeException('Provisioning role cannot assign ownership to the client runtime role.');
            }
            $administrator = User::on(config('tenancy.central_connection'))->where('status', 'active')->whereNull('deleted_at')->whereNull('platform_user_id')
                ->whereHas('roles', fn ($query) => $query->where('slug', 'super_admin')->where('user_roles.scope', 'global'))->orderBy('id')->first();
            $adminAttributes = $administrator?->getAttributes();
            if ($adminAttributes) {
                $adminAttributes['remember_token'] = null;
            }
            $lastCompany = (int) $connection->table('companies')->max('id');
            $lastUser = (int) $connection->table('users')->max('id');
            $oldClients = $schema->hasTable('platform_clients') ? $connection->table('platform_clients')->get(['id', 'uuid', 'client_code', 'database_name'])->all() : [];
            $lastClient = max([0, ...array_map(fn ($client) => (int) $client->id, $oldClients)]);
            $archive = 'hrm_test_backup_'.now()->format('Ymd_His').'_'.Str::lower(Str::random(8));
            $password = Str::random(40);
            $directory = config('tenancy.hosted_test_reset_directory', storage_path('app/private/hosted-test-resets'));
            File::ensureDirectoryExists($directory, 0700, true);
            $manifestPath = $directory.'/'.$archive.'.json';
            $manifest = [
                'status' => 'preparing', 'database' => $connection->getDatabaseName(), 'archive_schema' => $archive,
                'previous_client_databases' => $oldClients,
                'platform_username' => $administrator?->username ?? 'sys.admin',
                'platform_password' => $administrator ? 'unchanged' : $password,
                'test_client_password' => $password,
                'test_client_usernames' => [env('SEED_COMPANY_ADMIN_USERNAME', 'com.admin'), 'emp.aisha', 'hr.manager', 'payroll.manager', 'department.manager', 'employee.demo'],
            ];
            $writeManifest = function () use ($manifestPath, &$manifest) {
                if (file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false || ! chmod($manifestPath, 0600)) {
                    throw new RuntimeException('Cannot write the private reset recovery file; reset refused.');
                }
            };
            $writeManifest();
            // Preserve all old tables and FK relationships. No DROP SCHEMA/CASCADE is used.
            $connection->transaction(function () use ($connection, $archive) {
                $connection->statement('ALTER SCHEMA "public" RENAME TO "'.$archive.'"');
                $connection->statement('REVOKE ALL ON SCHEMA "'.$archive.'" FROM PUBLIC');
                $connection->statement('CREATE SCHEMA "public"');
            });
            $manifest['status'] = 'archived';
            $writeManifest();
            $this->info('Previous schema archived as '.$archive.'. Existing client databases and stored files are retained.');
            if ($this->call('migrate', ['--force' => true]) !== self::SUCCESS) {
                throw new RuntimeException('Fresh platform migration failed. Keep maintenance enabled and inspect the archive.');
            }
            // New IDs prevent old client sessions and file paths from resolving to new test records.
            foreach (['companies' => $lastCompany + 1, 'platform_clients' => $lastClient + 1, 'users' => $lastUser + 1] as $table => $next) {
                $connection->selectOne('SELECT setval(pg_get_serial_sequence(?, ?), ?, false)', [$table, 'id', max(1, $next)]);
            }
            $seeder = app(IsolatedDemoClientsSeeder::class)->setContainer(app())->setCommand($this);
            $seeder->runForHostedTestReset($adminAttributes, $password);
            $connection->selectOne("SELECT setval(pg_get_serial_sequence('users', 'id'), GREATEST((SELECT max(id) FROM users), ?))", [$lastUser]);
            if ($this->call('clients:sync-logins') !== self::SUCCESS) {
                throw new RuntimeException('Sign-in lookup rebuild failed. Keep maintenance enabled.');
            }
            $manifest['status'] = 'completed';
            $writeManifest();
            $this->info('Hosted test reset completed. Platform admin credentials were '.($administrator ? 'preserved.' : 'generated.'));
            $this->info('Private test credentials and archive details: '.$manifestPath);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());
            if ($manifestPath) {
                $this->warn('Recovery details are in '.$manifestPath.'. Keep maintenance enabled until the reset is verified.');
            }

            return self::FAILURE;
        }
    }
}
