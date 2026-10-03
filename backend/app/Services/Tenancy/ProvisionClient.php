<?php

namespace App\Services\Tenancy;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyComplianceSetting;
use App\Models\CompanySubscription;
use App\Models\LegalRuleSet;
use App\Models\OnboardingTemplate;
use App\Models\PlatformClient;
use App\Models\Role;
use App\Models\SalaryComponent;
use App\Models\User;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\LegalRuleSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\WpsProviderSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProvisionClient
{
    public function __construct(private TenantDatabaseManager $databases, private CreateTenantDatabase $creator) {}

    public function register(array $data, User $actor): PlatformClient
    {
        // 1. Reserve a client and billing identity centrally before creating a database.
        $client = DB::connection(config('tenancy.central_connection'))->transaction(function () use ($data, $actor) {
            $company = Company::on(config('tenancy.central_connection'))->create([
                'name' => $data['company_name'], 'legal_name' => $data['company_name'],
                'country' => $data['country'] ?? 'AE', 'emirate' => $data['emirate'] ?? null,
                'timezone' => $data['timezone'] ?? 'Asia/Dubai',
            ]);
            $uuid = (string) Str::uuid();
            $client = PlatformClient::query()->create([
                'uuid' => $uuid, 'client_code' => $data['client_code'],
                'company_id' => $company->id, 'database_name' => 'hrm_client_'.str_replace('-', '', $uuid),
                'created_by' => $actor->id,
                'setup_payload' => [
                    'company' => $company->getAttributes(),
                    'admin' => [
                        'name' => $data['admin_name'], 'username' => $data['admin_username'],
                        'email' => $data['admin_email'], 'password' => Hash::make($data['admin_password']),
                        'status' => 'active',
                    ],
                ],
            ]);
            if (! empty($data['subscription_plan_id'])) {
                CompanySubscription::on(config('tenancy.central_connection'))->create([
                    'company_id' => $company->id, 'subscription_plan_id' => $data['subscription_plan_id'],
                    'status' => 'trialing', 'starts_on' => today(),
                    'created_by' => $actor->id, 'updated_by' => $actor->id,
                ]);
            }
            $this->audit($client, $actor, 'client.created');

            return $client;
        });

        return $this->provision($client, $actor);
    }

    public function provision(PlatformClient $client, User $actor): PlatformClient
    {
        $lock = Cache::lock('provision-client:'.$client->uuid, 900);
        abort_unless($lock->get(), 409, 'Client setup is already running.');
        try {
            $client->refresh();
            if ($client->provisioning_status === 'ready') {
                return $client;
            }
            // 2. Keep a durable attempt record; a failed retry retains the same database.
            $client->update(['provisioning_status' => 'provisioning']);
            $run = $client->runs()->create([
                'status' => 'running', 'started_at' => now(), 'provisioned_by' => $actor->id,
                'snapshot' => ['client_code' => $client->client_code, 'company_id' => $client->company_id],
            ]);
            try {
                $created = $this->creator->create($client, $this->databases);
                $this->databases->run($client, function () use ($client, $run, $created) {
                    // 3. Never adopt an unrelated pre-existing database or seed it with sample data.
                    if (! Schema::hasTable('tenant_identity')) {
                        if (! $created) {
                            throw new RuntimeException('Existing database has no ownership marker; operator inspection required.');
                        }
                        Schema::create('tenant_identity', function (Blueprint $table) {
                            $table->unsignedInteger('id')->primary();
                            $table->uuid('uuid')->unique();
                            $table->timestamp('initialized_at')->nullable();
                        });
                        DB::table('tenant_identity')->insert(['id' => 1, 'uuid' => $client->uuid]);
                    }
                    if (DB::table('tenant_identity')->value('uuid') !== $client->uuid) {
                        throw new RuntimeException('Client database ownership mismatch.');
                    }
                    $run->update(['step' => 'migrations']);
                    $this->migrate($client);
                    if (! DB::table('tenant_identity')->value('initialized_at')) {
                        $run->update(['step' => 'defaults']);
                        $this->initialize($client);
                    }
                }, provisioning: true);
                // 4. Only a fully initialized client becomes available; discard bootstrap credentials.
                $client->update(['provisioning_status' => 'ready', 'ready_at' => now(), 'setup_payload' => null]);
                $run->update(['status' => 'completed', 'step' => 'ready', 'completed_at' => now()]);
                $this->audit($client, $actor, 'client.provisioned');
            } catch (Throwable $exception) {
                $client->update(['provisioning_status' => 'failed']);
                $run->update([
                    'status' => 'failed', 'failed_at' => now(),
                    'error_message' => 'Setup failed during '.$run->step.'. Check server logs for provisioning run '.$run->id.'.',
                ]);
                report($exception);
                $this->audit($client, $actor, 'client.provisioning_failed');
            }

            return $client->refresh();
        } finally {
            $lock->release();
        }
    }

    public function migrate(PlatformClient $client): void
    {
        $migrator = app('migrator');
        $migrator->usingConnection($this->databases->connectionName($client), function () use ($migrator) {
            if (! $migrator->repositoryExists()) {
                $migrator->getRepository()->createRepository();
            }
            // Platform migrations live in a separate directory and are never installed in a client DB.
            $migrator->run([database_path('migrations')], ['force' => true]);
        });
    }

    private function initialize(PlatformClient $client): void
    {
        $payload = $client->setup_payload;
        if (! $payload) {
            throw new RuntimeException('Missing client initialization details.');
        }
        DB::transaction(function () use ($payload, $client) {
            foreach ([LegalRuleSeeder::class, RoleAndPermissionSeeder::class, LeaveTypeSeeder::class, WpsProviderSeeder::class] as $seeder) {
                app($seeder)->run();
            }
            $company = new Company;
            $company->forceFill($payload['company'])->save();
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("SELECT setval(pg_get_serial_sequence('companies', 'id'), (SELECT max(id) FROM companies))");
            }
            $admin = User::query()->create($payload['admin']);
            $role = Role::query()->where('slug', 'company_admin')->whereNull('company_id')->firstOrFail();
            $admin->roles()->attach($role->id, ['company_id' => $company->id, 'scope' => 'company']);
            Branch::query()->create(['company_id' => $company->id, 'name' => 'Main branch', 'code' => 'MAIN', 'created_by' => $admin->id]);
            CompanyComplianceSetting::query()->create([
                'company_id' => $company->id, 'legal_rule_set_id' => LegalRuleSet::query()->latest('id')->value('id'),
                'created_by' => $admin->id,
            ]);
            OnboardingTemplate::query()->create([
                'company_id' => $company->id, 'name' => 'Employee onboarding', 'is_default' => true, 'created_by' => $admin->id,
            ]);
            SalaryComponent::query()->create(['company_id' => $company->id, 'code' => 'BASIC', 'name' => 'Basic salary', 'type' => 'earning']);
            DB::table('tenant_identity')->where('uuid', $client->uuid)->update(['initialized_at' => now()]);
        });
    }

    private function audit(PlatformClient $client, User $actor, string $action): void
    {
        AuditLog::on(config('tenancy.central_connection'))->create([
            'company_id' => $client->company_id, 'actor_user_id' => $actor->id, 'action' => $action,
            'auditable_type' => PlatformClient::class, 'auditable_id' => $client->id,
            'after_json' => ['status' => $client->provisioning_status, 'client_code' => $client->client_code],
        ]);
    }
}
