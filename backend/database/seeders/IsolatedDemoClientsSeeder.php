<?php

namespace Database\Seeders;

use App\Models\BillingInvoice;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\Document;
use App\Models\PlatformClient;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\TemporaryWorker;
use App\Models\User;
use App\Services\TemporaryWorkers\TemporaryWorkerLedger;
use App\Services\Tenancy\ProvisionClient;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class IsolatedDemoClientsSeeder extends Seeder
{
    private ?string $hostedPassword = null;

    private ?array $hostedAdmin = null;

    public function runForHostedTestReset(?array $administrator, string $password): void
    {
        if (! app()->isDownForMaintenance() || strlen($password) < 32) {
            throw new RuntimeException('Hosted test reset requires maintenance and a generated strong password.');
        }
        $this->hostedPassword = $password;
        $this->hostedAdmin = $administrator;
        try {
            $this->run();
        } finally {
            $this->hostedPassword = null;
            $this->hostedAdmin = null;
        }
    }

    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) && ! $this->hostedPassword) {
            throw new RuntimeException('Isolated demo data is restricted to local/testing environments.');
        }
        if (Company::exists() || PlatformClient::exists() || User::exists()) {
            throw new RuntimeException('Use a fresh central database; existing records will not be overwritten.');
        }
        $this->call([RoleAndPermissionSeeder::class, WpsProviderSeeder::class]);
        $admin = new User;
        $admin->forceFill($this->hostedAdmin ?? [
            'name' => 'System Admin', 'username' => 'sys.admin', 'email' => 'sys.admin@example.local',
            'password' => $this->hostedPassword ?? 'sys.admin', 'status' => 'active',
        ])->save();
        $admin->roles()->attach(Role::where('slug', 'super_admin')->firstOrFail(), ['scope' => 'global']);
        $plan = SubscriptionPlan::create([
            'code' => 'test-growth', 'name' => 'Test Growth', 'billing_cycle' => 'monthly',
            'price' => 799, 'currency' => 'AED', 'max_employees' => 250,
            'features_json' => ['hrm', 'attendance', 'leave', 'payroll', 'compliance'], 'status' => 'active',
        ]);
        foreach (['demo' => 'Demo UAE Company', 'retail' => 'Gulf Retail Test LLC', 'logistics' => 'Northern Logistics Test FZE'] as $code => $name) {
            $client = app(ProvisionClient::class)->register([
                'client_code' => $code, 'company_name' => $name, 'country' => 'AE', 'emirate' => 'Dubai',
                'admin_name' => 'Company Admin', 'admin_username' => env('SEED_COMPANY_ADMIN_USERNAME', 'com.admin'),
                'admin_email' => 'com.admin@example.local', 'admin_password' => $this->hostedPassword ?? env('SEED_COMPANY_ADMIN_PASSWORD', 'com.admin'),
                'subscription_plan_id' => $plan->id,
            ], $admin);
            if ($client->provisioning_status !== 'ready') {
                throw new RuntimeException('Provisioning failed for '.$code.'; inspect the provisioning run.');
            }
            app(TenantDatabaseManager::class)->run($client, function () use ($client) {
                $this->call([DemoDataSeeder::class, DashboardScenarioSeeder::class]);
                $actor = User::where('username', env('SEED_COMPANY_ADMIN_USERNAME', 'com.admin'))->firstOrFail();
                $request = Request::create('/demo-seeder');
                $request->setUserResolver(fn () => $actor);
                foreach (['daily' => 'Event setup assistant', 'hourly' => 'Warehouse support'] as $basis => $name) {
                    $worker = TemporaryWorker::create([
                        'company_id' => $client->company_id, 'branch_id' => Branch::where('company_id', $client->company_id)->firstOrFail()->id,
                        'name' => $name, 'job_description' => 'Synthetic test worker', 'wage_basis' => $basis,
                        'rate' => $basis === 'daily' ? 180 : 25, 'currency' => 'AED', 'status' => 'active',
                    ]);
                    $ledger = app(TemporaryWorkerLedger::class);
                    foreach ([3, 2, 1] as $daysAgo) {
                        $ledger->record($request, $worker, ['kind' => 'work', 'date' => today()->subDays($daysAgo)->toDateString(), 'quantity' => $basis === 'daily' ? '1.00' : '8.00', 'entry_key' => (string) Str::uuid()]);
                    }
                    $ledger->record($request, $worker, ['kind' => 'payment', 'date' => today()->toDateString(), 'amount' => '100.00', 'entry_key' => (string) Str::uuid()]);
                }
                // Use distinct company paths and real synthetic PDF bytes for download/preview checks.
                $pdf = $this->samplePdf();
                foreach (Document::all() as $document) {
                    $path = 'companies/'.$client->company_id.'/test-documents/'.$document->id.'.pdf';
                    if (! Storage::disk('local')->put($path, $pdf)) {
                        throw new RuntimeException('Could not write synthetic document.');
                    }
                    $document->update(['path' => $path, 'size_bytes' => strlen($pdf)]);
                }
                if ($this->hostedPassword) {
                    $passwordHash = Hash::make($this->hostedPassword);
                    foreach (User::where('status', 'active')->get() as $user) {
                        $user->password = $passwordHash;
                        $user->save();
                    }
                }
            });
            $subscription = CompanySubscription::where('company_id', $client->company_id)->firstOrFail();
            $subscription->update(['status' => $code === 'retail' ? 'trialing' : 'active', 'trial_ends_on' => today()->addDays(14)]);
            foreach (['paid', 'overdue', 'open'] as $index => $status) {
                BillingInvoice::create([
                    'company_id' => $client->company_id, 'company_subscription_id' => $subscription->id,
                    'invoice_number' => strtoupper($code).'-TEST-'.($index + 1),
                    'issue_date' => today()->subDays(60 - $index * 30), 'due_date' => today()->addDays($status === 'open' ? 14 : -14),
                    'paid_at' => $status === 'paid' ? now()->subDays(30) : null,
                    'subtotal' => 799, 'tax_amount' => 39.95, 'total_amount' => 838.95,
                    'currency' => 'AED', 'status' => $status, 'notes' => 'Synthetic local test invoice.', 'created_by' => $admin->id,
                ]);
            }
            $this->command?->info('Ready: '.$code.' → '.$client->database_name);
        }
    }

    private function samplePdf(): string
    {
        $stream = 'BT /F1 16 Tf 50 750 Td (SYNTHETIC HRM TEST DOCUMENT - NOT VALID ID) Tj ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($stream).">>\nstream\n".$stream."\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
