<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Resources\BillingInvoiceResource;
use App\Http\Resources\CompanySubscriptionResource;
use App\Services\Auth\CompanyAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CompanyBillingController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(private readonly CompanyAccess $access)
    {
    }

    public function current(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, 'companies.view');
        $company = $this->access->ensureCompany($user);

        $subscription = $company->subscriptions()->with('plan')->latest('id')->first();
        $invoices = $company->billingInvoices()->latest('issue_date')->latest('id')->limit(5)->get();

        return $this->success('Company billing retrieved.', [
            'company_subscription' => $subscription ? new CompanySubscriptionResource($subscription) : null,
            'billing_invoices' => BillingInvoiceResource::collection($invoices),
        ]);
    }
}
