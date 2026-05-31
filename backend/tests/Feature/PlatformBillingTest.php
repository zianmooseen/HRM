<?php

namespace Tests\Feature;

use App\Models\BillingInvoice;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_plan_assign_subscription_and_manage_invoice(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $company = Company::query()->create(['name' => 'Client Company']);
        $superAdmin = $this->userWithRole('super_admin');

        Sanctum::actingAs($superAdmin);

        $planId = $this->postJson('/api/platform/billing/plans', [
            'name' => 'Growth',
            'code' => 'growth',
            'billing_cycle' => 'monthly',
            'price' => 499,
            'currency' => 'AED',
            'max_employees' => 100,
            'features' => ['HRM', 'Payroll'],
            'status' => 'active',
        ])->assertCreated()
            ->assertJsonPath('data.subscription_plan.code', 'growth')
            ->json('data.subscription_plan.id');

        $subscriptionId = $this->postJson("/api/platform/billing/companies/{$company->id}/subscription", [
            'subscription_plan_id' => $planId,
            'status' => 'active',
            'starts_on' => '2026-06-01',
            'current_period_starts_on' => '2026-06-01',
            'current_period_ends_on' => '2026-06-30',
            'notes' => 'Initial paid subscription.',
        ])->assertCreated()
            ->assertJsonPath('data.company_subscription.status', 'active')
            ->assertJsonPath('data.company_subscription.plan.code', 'growth')
            ->json('data.company_subscription.id');

        $invoiceId = $this->postJson("/api/platform/billing/companies/{$company->id}/invoices", [
            'company_subscription_id' => $subscriptionId,
            'invoice_number' => 'INV-2026-0001',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'subtotal' => 499,
            'tax_amount' => 24.95,
            'total_amount' => 523.95,
            'currency' => 'AED',
            'status' => 'open',
        ])->assertCreated()
            ->assertJsonPath('data.billing_invoice.invoice_number', 'INV-2026-0001')
            ->json('data.billing_invoice.id');

        $this->postJson("/api/platform/billing/invoices/{$invoiceId}/mark-paid")
            ->assertOk()
            ->assertJsonPath('data.billing_invoice.status', 'paid');

        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'company_subscription.assigned']);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'billing_invoice.created']);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'billing_invoice.paid']);
    }

    public function test_company_admin_cannot_manage_platform_billing(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $company = Company::query()->create(['name' => 'Client Company']);
        $companyAdmin = $this->userWithRole('company_admin', $company);

        Sanctum::actingAs($companyAdmin);

        $this->postJson('/api/platform/billing/plans', [
            'name' => 'Starter',
            'code' => 'starter',
            'billing_cycle' => 'monthly',
            'price' => 199,
            'currency' => 'AED',
            'status' => 'active',
        ])->assertForbidden();
    }

    public function test_company_admin_can_view_current_company_billing_summary(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $company = Company::query()->create(['name' => 'Client Company']);
        $plan = SubscriptionPlan::query()->create([
            'name' => 'Starter',
            'code' => 'starter',
            'billing_cycle' => 'monthly',
            'price' => 199,
            'currency' => 'AED',
            'status' => 'active',
        ]);
        $subscription = CompanySubscription::query()->create([
            'company_id' => $company->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'trialing',
            'starts_on' => '2026-06-01',
            'trial_ends_on' => '2026-06-15',
        ]);
        BillingInvoice::query()->create([
            'company_id' => $company->id,
            'company_subscription_id' => $subscription->id,
            'invoice_number' => 'INV-2026-0002',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'subtotal' => 199,
            'tax_amount' => 9.95,
            'total_amount' => 208.95,
            'currency' => 'AED',
            'status' => 'open',
        ]);
        $companyAdmin = $this->userWithRole('company_admin', $company);

        Sanctum::actingAs($companyAdmin);

        $this->getJson('/api/billing/current')
            ->assertOk()
            ->assertJsonPath('data.company_subscription.status', 'trialing')
            ->assertJsonPath('data.company_subscription.plan.code', 'starter')
            ->assertJsonPath('data.billing_invoices.0.invoice_number', 'INV-2026-0002');
    }

    private function userWithRole(string $roleSlug, ?Company $company = null): User
    {
        $user = User::factory()->create();
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

        $user->roles()->attach($role->id, [
            'company_id' => $company?->id,
            'scope' => $roleSlug === 'super_admin' ? 'global' : 'company',
        ]);

        return $user;
    }
}
