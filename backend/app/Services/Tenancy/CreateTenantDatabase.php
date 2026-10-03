<?php

namespace App\Services\Tenancy;

use App\Models\PlatformClient;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreateTenantDatabase
{
    public function create(PlatformClient $client, TenantDatabaseManager $manager): bool
    {
        $config = $manager->configuration($client);
        if ($config['driver'] === 'sqlite') {
            $directory = dirname($config['database']);
            if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                throw new RuntimeException('Cannot create the client database directory.');
            }
            if (is_file($config['database'])) {
                return false;
            }
            $file = fopen($config['database'], 'x');
            if ($file === false) {
                throw new RuntimeException('Cannot create the client database file.');
            }
            fclose($file);
            chmod($config['database'], 0600);

            return true;
        }

        // Database creation must happen outside a transaction (required by PostgreSQL).
        $connection = DB::connection(config('tenancy.provisioning_connection'));
        if ($connection->getDriverName() !== $config['driver']) {
            throw new RuntimeException('Provisioning and runtime database drivers must match.');
        }
        $name = $client->database_name; // Validated, server-generated identifier.
        if ($config['driver'] === 'pgsql') {
            if ($connection->selectOne('select 1 from pg_database where datname = ?', [$name])) {
                return false;
            }
            $owner = str_replace('"', '""', $config['username']);
            $connection->statement('CREATE DATABASE "'.$name.'" OWNER "'.$owner.'"');
        } elseif ($config['driver'] === 'mysql') {
            if ($connection->selectOne('select 1 from information_schema.schemata where schema_name = ?', [$name])) {
                return false;
            }
            $connection->statement('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } else {
            throw new RuntimeException('Client provisioning supports PostgreSQL, MySQL and local SQLite.');
        }

        return true;
    }
}
