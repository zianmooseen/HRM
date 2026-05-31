<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Resources\BillingInvoiceResource;
use App\Http\Resources\CompanySubscriptionResource;
use App\Http\Resources\SubscriptionPlanResource;
use App\Models\BillingInvoice;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\SubscriptionPlan;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class PlatformBillingController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function plans(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin($request);

        return $this->success('Subscription plans retrieved.', [
            'subscription_plans' => SubscriptionPlanResource::collection(
                SubscriptionPlan::query()->orderBy('price')->orderBy('name')->get()
            ),
        ]);
    }

    public function storePlan(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', 'unique:subscription_plans,code'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'max_employees' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $plan = SubscriptionPlan::query()->create([
            ...$data,
            'features_json' => $data['features'] ?? [],
        ]);

        return $this->success('Subscription plan created.', [
            'subscription_plan' => new SubscriptionPlanResource($plan),
        ], 201);
    }

    public function subscriptions(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin($request);

        return $this->success('Company subscriptions retrieved.', [
            'company_subscriptions' => CompanySubscriptionResource::collection(
                CompanySubscription::query()->with(['company', 'plan'])->latest('id')->get()
            ),
        ]);
    }

    public function assignSubscription(Request $request, Company $company): JsonResponse
    {
        $this->ensureSuperAdmin($request);
        $data = $request->validate([
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'status' => ['required', 'in:trialing,active,past_due,cancelled'],
            'starts_on' => ['required', 'date'],
            'trial_ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'current_period_starts_on' => ['nullable', 'date'],
            'current_period_ends_on' => ['nullable', 'date', 'after_or_equal:current_period_starts_on'],
            'cancelled_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $subscription = CompanySubscription::query()->create([
            ...$data,
            'company_id' => $company->id,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $this->audit->log($request, 'company_subscription.assigned', $subscription, null, $subscription->toArray());

        return $this->success('Company subscription assigned.', [
            'company_subscription' => new CompanySubscriptionResource($subscription->load(['company', 'plan'])),
        ], 201);
    }

    public function invoices(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin($request);

        return $this->success('Billing invoices retrieved.', [
            'billing_invoices' => BillingInvoiceResource::collection(
                BillingInvoice::query()->with(['company', 'subscription.plan'])->latest('issue_date')->latest('id')->get()
            ),
        ]);
    }

    public function storeInvoice(Request $request, Company $company): JsonResponse
    {
        $this->ensureSuperAdmin($request);
        $data = $request->validate([
            'company_subscription_id' => [
                'nullable',
                'integer',
                Rule::exists('company_subscriptions', 'id')->where('company_id', $company->id),
            ],
            'invoice_number' => ['required', 'string', 'max:100', 'unique:billing_invoices,invoice_number'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'status' => ['required', 'in:draft,open,paid,void,uncollectible'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $invoice = BillingInvoice::query()->create([
            ...$data,
            'company_id' => $company->id,
            'tax_amount' => $data['tax_amount'] ?? 0,
            'paid_at' => $data['status'] === 'paid' ? now() : null,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $this->audit->log($request, 'billing_invoice.created', $invoice, null, $invoice->toArray());

        return $this->success('Billing invoice created.', [
            'billing_invoice' => new BillingInvoiceResource($invoice->load(['company', 'subscription.plan'])),
        ], 201);
    }

    public function markInvoicePaid(Request $request, BillingInvoice $invoice): JsonResponse
    {
        $this->ensureSuperAdmin($request);
        $before = $invoice->toArray();
        $invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
            'updated_by' => $request->user()->id,
        ]);

        $this->audit->log($request, 'billing_invoice.paid', $invoice, $before, $invoice->fresh()->toArray());

        return $this->success('Billing invoice marked paid.', [
            'billing_invoice' => new BillingInvoiceResource($invoice->fresh()->load(['company', 'subscription.plan'])),
        ]);
    }

    private function ensureSuperAdmin(Request $request): void
    {
        $user = $request->user()->loadMissing('roles.permissions');
        abort_unless($user->hasRole('super_admin'), 403, 'You are not authorized to perform this action.');
    }
}
