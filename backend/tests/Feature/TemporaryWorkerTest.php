<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TemporaryWorker;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TemporaryWorkerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Branch $branch;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->company = Company::query()->create(['name' => 'Temporary Workers Co', 'default_currency' => 'AED', 'timezone' => 'Asia/Dubai']);
        $this->branch = Branch::query()->create(['company_id' => $this->company->id, 'name' => 'Dubai', 'code' => 'DXB']);
        $this->user = $this->login('company_admin');
    }

    private function login(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $role)->firstOrFail()->id, ['company_id' => $this->company->id, 'scope' => 'company']);
        Sanctum::actingAs($user);

        return $user;
    }

    private function profile(array $overrides = []): array
    {
        return array_merge(['name' => 'Sam Worker', 'branch_id' => $this->branch->id, 'wage_basis' => 'hourly', 'rate' => '12.35', 'status' => 'active'], $overrides);
    }

    private function worker(array $overrides = []): int
    {
        return $this->postJson('/api/temporary-workers', $this->profile($overrides))->assertCreated()->json('data.worker.id');
    }

    private function entry(int $id, array $data = [])
    {
        return $this->postJson("/api/temporary-workers/{$id}/entries", array_merge(['entry_key' => (string) Str::uuid(), 'kind' => 'work', 'date' => '2026-01-01', 'quantity' => '7.50'], $data));
    }

    public function test_simple_profile_creates_no_employee_account_or_onboarding_and_rounds_earnings(): void
    {
        $users = User::count();
        $id = $this->worker();
        $this->assertDatabaseCount('employees', 0);
        $this->assertDatabaseCount('employee_onboarding_cases', 0);
        $this->assertSame($users, User::count());
        $this->entry($id)->assertCreated()->assertJsonPath('data.entry.amount', '92.63');
        $this->getJson("/api/temporary-workers/{$id}")->assertOk()
            ->assertJsonPath('data.totals.hours', '7.50')->assertJsonPath('data.totals.days', '0.00')
            ->assertJsonPath('data.totals.earned', '92.63')->assertJsonPath('data.totals.first_worked', '2026-01-01');
        $this->getJson('/api/temporary-workers')->assertOk()->assertJsonPath('data.counts.total', 1)
            ->assertJsonPath('data.workers.0.branch.name', 'Dubai');
    }

    public function test_rate_and_basis_changes_preserve_old_entries_and_currency(): void
    {
        $id = $this->worker();
        $this->entry($id)->assertCreated();
        $this->company->update(['default_currency' => 'USD']);
        $this->putJson("/api/temporary-workers/{$id}", $this->profile(['wage_basis' => 'daily', 'rate' => '100']))->assertOk();
        $this->entry($id, ['date' => '2026-01-02', 'quantity' => '0.5'])->assertCreated()->assertJsonPath('data.entry.amount', '50.00');
        $this->getJson("/api/temporary-workers/{$id}")->assertOk()->assertJsonPath('data.worker.currency', 'AED')
            ->assertJsonPath('data.totals.earned', '142.63')->assertJsonPath('data.totals.hours', '7.50')
            ->assertJsonPath('data.totals.days', '0.50')->assertJsonPath('data.entries.1.rate', '12.35');
    }

    public function test_payments_cannot_exceed_earnings_and_duplicate_keys_are_rejected(): void
    {
        $id = $this->worker();
        $payment = ['kind' => 'payment', 'amount' => '50.00', 'method' => 'cash', 'entry_key' => (string) Str::uuid()];
        $this->entry($id, $payment)->assertUnprocessable();
        $this->entry($id)->assertCreated();
        $this->entry($id, $payment)->assertCreated();
        $this->entry($id, $payment)->assertUnprocessable();
        $this->entry($id, ['kind' => 'payment', 'amount' => '42.64', 'method' => 'cash'])->assertUnprocessable();
        $this->getJson("/api/temporary-workers/{$id}")->assertJsonPath('data.totals.paid', '50.00')->assertJsonPath('data.totals.balance', '42.63');
        $this->assertDatabaseCount('temporary_worker_entries', 2);
    }

    public function test_voids_keep_history_and_do_not_allow_paid_work_to_be_removed(): void
    {
        $id = $this->worker();
        $work = $this->entry($id)->assertCreated()->json('data.entry.id');
        $pay = $this->entry($id, ['kind' => 'payment', 'amount' => '92.63', 'method' => 'bank_transfer'])->assertCreated()->json('data.entry.id');
        $this->postJson("/api/temporary-workers/{$id}/entries/{$work}/void", ['reason' => 'Wrong work date'])->assertUnprocessable();
        $this->postJson("/api/temporary-workers/{$id}/entries/{$pay}/void", ['reason' => 'Payment entered in error'])->assertOk();
        $this->postJson("/api/temporary-workers/{$id}/entries/{$work}/void", ['reason' => 'Wrong work date'])->assertOk();
        $this->postJson("/api/temporary-workers/{$id}/entries/{$work}/void", ['reason' => 'Again'])->assertUnprocessable();
        $this->assertDatabaseCount('temporary_worker_entries', 2);
        $this->getJson("/api/temporary-workers/{$id}")->assertJsonPath('data.totals.earned', '0.00')->assertJsonPath('data.totals.paid', '0.00');
        $this->entry($id)->assertCreated();
    }

    public function test_validation_duplicate_dates_inactive_workers_and_branch_history(): void
    {
        $id = $this->worker(['wage_basis' => 'daily', 'rate' => '100']);
        $this->entry($id, ['quantity' => '1.01'])->assertUnprocessable();
        $this->entry($id, ['quantity' => '1', 'date' => now()->addDays(2)->toDateString()])->assertUnprocessable();
        $this->entry($id, ['quantity' => '0.123'])->assertUnprocessable();
        $this->entry($id, ['quantity' => '1'])->assertCreated();
        $this->entry($id, ['quantity' => '1'])->assertUnprocessable();
        $branch = Branch::create(['company_id' => $this->company->id, 'name' => 'Other', 'code' => 'OTH']);
        $this->putJson("/api/temporary-workers/{$id}", $this->profile(['branch_id' => $branch->id]))->assertUnprocessable();
        $this->putJson("/api/temporary-workers/{$id}", $this->profile(['status' => 'inactive']))->assertOk();
        $this->entry($id, ['date' => '2026-01-02'])->assertUnprocessable();
        $this->entry($id, ['kind' => 'payment', 'amount' => '100', 'method' => 'cash'])->assertCreated();
    }

    public function test_company_and_selected_branch_isolation(): void
    {
        $id = $this->worker();
        $other = Company::create(['name' => 'Other Co']);
        $branch = Branch::create(['company_id' => $other->id, 'name' => 'Other', 'code' => 'OTH']);
        $foreign = TemporaryWorker::create($this->profile(['branch_id' => $branch->id]) + ['company_id' => $other->id, 'currency' => 'AED']);
        $this->getJson("/api/temporary-workers/{$foreign->id}")->assertNotFound();
        $this->entry($foreign->id)->assertNotFound();
        $this->postJson('/api/temporary-workers', $this->profile(['branch_id' => $branch->id]))->assertUnprocessable();
        $second = Branch::create(['company_id' => $this->company->id, 'name' => 'Abu Dhabi', 'code' => 'AUH']);
        $scope = '?scope_company_id='.$this->company->id.'&scope_branch_id='.$second->id;
        $this->getJson('/api/temporary-workers'.$scope)->assertOk()->assertJsonPath('data.counts.total', 0);
        $this->getJson("/api/temporary-workers/{$id}".$scope)->assertNotFound();
        $this->postJson("/api/temporary-workers/{$id}/entries".$scope, ['entry_key' => (string) Str::uuid(), 'kind' => 'work', 'date' => '2026-01-01', 'quantity' => 1])->assertNotFound();
    }

    public function test_work_and_payment_permissions_are_independent_and_entries_cannot_be_cross_voided(): void
    {
        $id = $this->worker();
        $other = $this->worker(['name' => 'Other Worker']);
        $entry = $this->entry($other)->assertCreated()->json('data.entry.id');
        $this->postJson("/api/temporary-workers/{$id}/entries/{$entry}/void", ['reason' => 'Wrong worker'])->assertNotFound();
        $this->entry($id)->assertCreated();
        $role = Role::where('slug', 'payroll_manager')->firstOrFail();
        $role->permissions()->detach(Permission::where('slug', 'temporary_workers.pay')->value('id'));
        $this->login('payroll_manager');
        $this->entry($id, ['kind' => 'payment', 'amount' => '1', 'method' => 'cash'])->assertForbidden();
        $this->entry($id, ['date' => '2026-01-02'])->assertCreated();
    }

    public function test_permissions_audit_privacy_and_named_worker_reference(): void
    {
        $id = $this->worker();
        $this->entry($id)->assertCreated();
        $log = AuditLog::where('action', 'temporary_worker_work.created')->firstOrFail();
        $response = $this->getJson('/api/audit-logs/'.$log->id)->assertOk();
        $this->assertSame('Sam Worker', $response->json('data.audit_log.reference_labels.temporary_worker_id.'.$id));
        $this->login('hr_manager');
        $this->getJson('/api/temporary-workers')->assertForbidden();
        $this->getJson('/api/audit-logs/'.$log->id)->assertOk()->assertJsonPath('data.audit_log.snapshots_visible', false);
        $this->login('employee');
        $this->getJson("/api/temporary-workers/{$id}")->assertForbidden();
        $this->entry($id)->assertForbidden();
        $this->login('payroll_manager');
        $this->getJson('/api/temporary-workers')->assertOk();
        $this->entry($id, ['kind' => 'payment', 'amount' => '20', 'method' => 'cash'])->assertCreated();
    }
}
