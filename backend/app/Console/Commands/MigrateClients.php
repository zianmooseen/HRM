<?php

namespace App\Console\Commands;

use App\Models\PlatformClient;
use App\Services\Tenancy\ProvisionClient;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Cache;
use Throwable;

class MigrateClients extends Command
{
    use ConfirmableTrait;

    protected $signature = 'clients:migrate {--client= : Limit to a client code} {--force : Run in production}';

    protected $description = 'Apply HRM schema migrations to paused, provisioned client databases';

    public function handle(TenantDatabaseManager $databases, ProvisionClient $provisioner): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }
        $clients = PlatformClient::where('provisioning_status', 'ready')
            ->when($this->option('client'), fn ($query) => $query->where('client_code', $this->option('client')))->get();
        if ($this->option('client') && $clients->isEmpty()) {
            $this->error('No ready client matches that code.');

            return self::FAILURE;
        }
        if ($clients->contains(fn ($client) => ! $client->maintenance_at)) {
            $this->error('Pause the selected clients with clients:maintenance before migrating.');

            return self::FAILURE;
        }
        $failed = false;
        foreach ($clients as $client) {
            try {
                Cache::lock('provision-client:'.$client->uuid, 900)->block(1, function () use ($client, $databases, $provisioner) {
                    $databases->run($client, function () use ($client, $databases, $provisioner) {
                        $databases->assertIdentity($client);
                        $provisioner->migrate($client);
                    }, provisioning: true);
                });
                $this->info($client->client_code.': migrated (still paused).');
            } catch (Throwable $exception) {
                report($exception);
                $this->error($client->client_code.': migration failed; client remains paused.');
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
