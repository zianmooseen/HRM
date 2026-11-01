<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Employee;
use App\Models\PlatformClient;
use App\Models\User;
use App\Services\Tenancy\TenantDatabaseManager;
use Database\Seeders\IsolatedDemoClientsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IsolatedDemoClientsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $legacySchema = false;

    public function test_existing_records_are_never_overwritten(): void
    {
        $user = User::factory()->create();
        try {
            $this->seed(IsolatedDemoClientsSeeder::class);
            $this->fail('An occupied central database must be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('existing records will not be overwritten', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $user->email]);
        $this->assertDatabaseCount('platform_clients', 0);
    }

    public function test_fresh_demo_clients_have_isolated_hr_records_and_central_billing(): void
    {
        $directory = sys_get_temp_dir().'/hrm-demo-test-'.bin2hex(random_bytes(8));
        config([
            'tenancy.sqlite_directory' => $directory,
            'tenancy.template_connection' => 'demo_test_template',
            'database.connections.demo_test_template' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true, 'prefix' => ''],
        ]);
        Storage::fake('local');
        try {
            $this->seed(IsolatedDemoClientsSeeder::class);
            $this->assertDatabaseCount('platform_clients', 3);
            $this->assertDatabaseCount('companies', 3);
            $this->assertDatabaseCount('users', 1);
            $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('employees'));
            $this->assertDatabaseCount('billing_invoices', 9);
            $clients = PlatformClient::orderBy('id')->get();
            $this->assertCount(3, $clients->pluck('database_name')->unique());
            foreach ($clients as $client) {
                $this->assertSame('ready', $client->provisioning_status);
                app(TenantDatabaseManager::class)->run($client, function () use ($client) {
                    $this->assertDatabaseCount('companies', 1);
                    $this->assertDatabaseCount('employees', 160);
                    $this->assertSame([$client->company_id], Employee::distinct()->pluck('company_id')->all());
                    $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('billing_invoices'));
                    $this->assertDatabaseCount('temporary_workers', 2);
                    $this->assertDatabaseCount('temporary_worker_entries', 8);
                    $this->assertTrue(Hash::check('employee.demo', User::where('username', 'employee.demo')->firstOrFail()->password));
                    $this->assertFalse(User::where('username', 'sys.admin')->exists());
                    $document = Document::firstOrFail();
                    $this->assertStringStartsWith('companies/'.$client->company_id.'/', $document->path);
                    Storage::disk('local')->assertExists($document->path);
                    $this->assertSame($document->size_bytes, strlen(Storage::disk('local')->get($document->path)));
                    foreach (['attendance_records', 'leave_requests', 'payslips', 'wps_payroll_batches', 'employee_onboarding_cases', 'audit_logs'] as $table) {
                        $this->assertTrue(DB::table($table)->exists(), $table.' should contain test scenarios');
                    }
                });
            }
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
