<?php

namespace App\Console\Commands;

use App\Models\PlatformClient;
use Illuminate\Console\Command;

class ListClientDatabases extends Command
{
    protected $signature = 'clients:databases';

    protected $description = 'List client database names for backup and restore operators';

    public function handle(): int
    {
        $this->table(['Client code', 'Database', 'Setup', 'Paused'], PlatformClient::orderBy('client_code')->get()->map(
            fn ($client) => [$client->client_code, $client->database_name, $client->provisioning_status, $client->maintenance_at ? 'yes' : 'no']
        ));

        return self::SUCCESS;
    }
}
