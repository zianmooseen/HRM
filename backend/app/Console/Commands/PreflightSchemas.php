<?php

namespace App\Console\Commands;

use App\Models\PlatformClient;
use App\Services\Tenancy\SchemaLayout;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PreflightSchemas extends Command
{
    protected $signature = 'schema:preflight';

    protected $description = 'Check legacy data blockers before deployment enters maintenance';

    public function handle(TenantDatabaseManager $manager): int
    {
        try {
            SchemaLayout::preflightLegacyTables(platform: true);
            if (Schema::hasTable('platform_clients')) {
                foreach (PlatformClient::where('provisioning_status', 'ready')->get() as $client) {
                    $manager->run($client, function () use ($manager, $client) {
                        $manager->assertIdentity($client);
                        SchemaLayout::preflightLegacyTables(platform: false);
                    }, provisioning: true);
                }
            }
        } catch (\Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());
            $this->warn('No schema changes were made. Resolve legacy data or explicitly reset disposable hosted test data.');

            return self::FAILURE;
        }
        $this->info('Schema preflight passed.');

        return self::SUCCESS;
    }
}
