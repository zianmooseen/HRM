<?php

namespace App\Console\Commands;

use App\Models\PlatformClient;
use App\Models\User;
use App\Services\Tenancy\ClientLoginDirectory;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncClientLogins extends Command
{
    protected $signature = 'clients:sync-logins {--client= : Limit to a client code}';

    protected $description = 'Rebuild the central sign-in lookup from client users without copying credentials';

    public function handle(TenantDatabaseManager $manager, ClientLoginDirectory $directory): int
    {
        $clients = PlatformClient::where('provisioning_status', 'ready')
            ->when($this->option('client'), fn ($query) => $query->where('client_code', $this->option('client')))->get();
        if ($this->option('client') && $clients->isEmpty()) {
            $this->error('No ready client matches that code.');

            return self::FAILURE;
        }
        foreach ($clients as $client) {
            try {
                $manager->run($client, function () use ($client, $manager, $directory) {
                    $manager->assertIdentity($client);
                    DB::connection(config('tenancy.central_connection'))->transaction(function () use ($client, $directory) {
                        DB::connection(config('tenancy.central_connection'))->table('client_login_identifiers')->where('platform_client_id', $client->id)->delete();
                        User::query()->chunkById(100, fn ($users) => $users->each(fn ($user) => $directory->sync($user)));
                    });
                }, provisioning: true);
            } catch (\Throwable $exception) {
                report($exception);
                $this->error('Could not synchronize sign-in lookup for '.$client->client_code.'.');

                return self::FAILURE;
            }
            $this->info($client->client_code.': sign-in lookup synchronized.');
        }

        return self::SUCCESS;
    }
}
