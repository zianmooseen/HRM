<?php

namespace App\Console\Commands;

use App\Models\PlatformClient;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Cache;

class UpgradeClients extends Command
{
    use ConfirmableTrait;

    protected $signature = 'clients:upgrade {--force : Run in production}';

    protected $description = 'Pause, migrate and verify all ready clients during application maintenance';

    public function handle(): int
    {
        if (! app()->isDownForMaintenance()) {
            $this->error('Put the application into maintenance and drain requests/jobs first.');

            return self::FAILURE;
        }
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        return Cache::lock('upgrade-client-schemas', 3600)->block(1, function () {
            $clients = PlatformClient::where('provisioning_status', 'ready')->orderBy('id')->get();
            $resume = $clients->whereNull('maintenance_at')->pluck('client_code')->all();
            foreach ($clients as $client) {
                if (! $client->maintenance_at && $this->call('clients:maintenance', ['client' => $client->client_code]) !== self::SUCCESS) {
                    return self::FAILURE;
                }
            }
            if ($this->call('clients:migrate', ['--force' => true]) !== self::SUCCESS) {
                $this->error('Client upgrade failed. Application and clients remain paused for inspection.');

                return self::FAILURE;
            }
            foreach ($resume as $code) {
                if ($this->call('clients:maintenance', ['client' => $code, '--resume' => true]) !== self::SUCCESS) {
                    return self::FAILURE;
                }
            }

            return self::SUCCESS;
        });
    }
}
