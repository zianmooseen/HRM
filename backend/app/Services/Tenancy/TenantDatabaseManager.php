<?php

namespace App\Services\Tenancy;

use App\Models\PlatformClient;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class TenantDatabaseManager
{
    private ?PlatformClient $current = null;

    public function current(): ?PlatformClient
    {
        return $this->current;
    }

    public function connectionName(PlatformClient $client): string
    {
        return 'client_'.$client->id;
    }

    public function configuration(PlatformClient $client): array
    {
        // Resolve URLs first, then remove overrides that could silently select the platform DB.
        $config = DB::connection(config('tenancy.template_connection'))->getConfig();
        unset($config['url'], $config['read'], $config['write'], $config['name']);
        if (! preg_match('/\Ahrm_client_[a-f0-9]{32}\z/', $client->database_name)) {
            throw new RuntimeException('Invalid managed database identifier.');
        }
        $config['database'] = $config['driver'] === 'sqlite'
            ? rtrim(config('tenancy.sqlite_directory'), '/').'/'.$client->database_name.'.sqlite'
            : $client->database_name;

        return $config;
    }

    public function run(PlatformClient $client, Closure $callback, bool $provisioning = false): mixed
    {
        if (! $provisioning && ($client->provisioning_status !== 'ready' || $client->maintenance_at)) {
            abort(503, 'This client is not available while setup or maintenance is in progress.');
        }
        if ($this->current?->id === $client->id) {
            return $callback();
        }
        $previousConnection = DB::getDefaultConnection();
        $previousClient = $this->current;
        $name = $this->connectionName($client);
        config(['database.connections.'.$name => $this->configuration($client)]);
        DB::purge($name);
        try {
            // Every query, validator and route binding now uses this client's database.
            $this->current = $client;
            DB::setDefaultConnection($name);
            Schema::clearResolvedInstance('db.schema');
            if (! $provisioning) {
                $this->assertIdentity($client);
            }

            return $callback();
        } finally {
            DB::setDefaultConnection($previousConnection);
            Schema::clearResolvedInstance('db.schema');
            $this->current = $previousClient;
            DB::purge($name);
        }
    }

    public function assertIdentity(PlatformClient $client): void
    {
        $identity = DB::table('tenant_identity')->first();
        if (! $identity || $identity->uuid !== $client->uuid || ! $identity->initialized_at) {
            throw new RuntimeException('Client database identity or initialization does not match.');
        }
    }
}
