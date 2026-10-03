<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\PlatformClient;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ClientMaintenance extends Command
{
    protected $signature = 'clients:maintenance {client : Client sign-in code} {--resume : Re-enable access after verifying the database}';

    protected $description = 'Pause one client and revoke its sessions, or resume it after maintenance';

    public function handle(TenantDatabaseManager $databases): int
    {
        $client = PlatformClient::where('client_code', $this->argument('client'))->first();
        if (! $client || $client->provisioning_status !== 'ready') {
            $this->error('Client must finish provisioning before maintenance.');

            return self::FAILURE;
        }
        try {
            if ($this->option('resume')) {
                $databases->run($client, fn () => $databases->assertIdentity($client), provisioning: true);
                $client->update(['maintenance_at' => null]);
            } else {
                DB::connection(config('tenancy.central_connection'))->transaction(function () use ($client) {
                    $client = PlatformClient::whereKey($client->id)->lockForUpdate()->firstOrFail();
                    $client->update(['maintenance_at' => now(), 'session_version' => $client->session_version + 1]);
                });
            }
            AuditLog::on(config('tenancy.central_connection'))->create([
                'company_id' => $client->company_id, 'action' => $this->option('resume') ? 'client.resumed' : 'client.maintenance_started',
                'auditable_type' => PlatformClient::class, 'auditable_id' => $client->id,
                'after_json' => ['source' => 'console', 'client_code' => $client->client_code],
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Client maintenance failed. Check server logs.');

            return self::FAILURE;
        }
        $this->info($this->option('resume') ? 'Client resumed. Users must sign in again.' : 'New client access is paused and sessions revoked. Drain in-flight requests and jobs before restoring.');

        return self::SUCCESS;
    }
}
