<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class PreparePostgresProvisioner extends Command
{
    protected $signature = 'clients:prepare-provisioner {--force : Grant CREATEDB to the configured local PostgreSQL provisioning role}';

    protected $description = 'Prepare local PostgreSQL database creation during an explicit hosted test reset';

    public function handle(): int
    {
        try {
            if (! $this->option('force') || ! app()->isDownForMaintenance()) {
                throw new RuntimeException('Provisioner setup requires --force and application maintenance.');
            }
            $connection = DB::connection(config('tenancy.provisioning_connection'));
            if ($connection->getDriverName() !== 'pgsql') {
                throw new RuntimeException('Provisioner setup only supports local PostgreSQL.');
            }
            $role = $connection->selectOne('SELECT current_user AS name, rolcreatedb, rolsuper FROM pg_roles WHERE rolname = current_user');
            if ($role->rolcreatedb || $role->rolsuper) {
                $this->info('PostgreSQL provisioner already has database creation permission.');

                return self::SUCCESS;
            }
            $host = $connection->getConfig('host');
            if (! in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
                throw new RuntimeException('Remote PostgreSQL must be configured by its database administrator.');
            }
            // Pass an argument array and quote the role as a PostgreSQL identifier.
            // Never grant SUPERUSER, CREATEROLE, or change the runtime password.
            $sql = 'ALTER ROLE "'.str_replace('"', '""', $role->name).'" CREATEDB';
            $result = Process::timeout(30)->run([
                'sudo', '-n', '-u', 'postgres', 'psql', '--no-psqlrc',
                '--port', (string) $connection->getConfig('port'), '--dbname', 'postgres',
                '--set', 'ON_ERROR_STOP=1', '--command', $sql,
            ]);
            if (! $result->successful()) {
                throw new RuntimeException('Server administrator access is required. Run as PostgreSQL administrator: '.$sql.'. Details: '.trim($result->errorOutput()));
            }
            $updated = $connection->selectOne('SELECT rolcreatedb, rolsuper FROM pg_roles WHERE rolname = current_user');
            if (! $updated->rolcreatedb && ! $updated->rolsuper) {
                throw new RuntimeException('Database creation permission could not be verified on the configured server.');
            }
            $this->info('Database creation permission granted to the configured PostgreSQL provisioner.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
