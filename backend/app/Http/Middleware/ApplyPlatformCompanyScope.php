<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\Auth\OrganizationContext;
use Closure;
use Illuminate\Http\Request;

class ApplyPlatformCompanyScope
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->query->has('scope_company_id') || $request->is('api/platform/clients*')) {
            return $next($request);
        }
        $request->validate(['scope_company_id' => ['required', 'integer', 'min:1']]);
        $company = Company::query()->findOrFail($request->integer('scope_company_id'));
        // Billing and platform reports are company-wide, even if an HR branch is selected.
        $request->attributes->set(OrganizationContext::class, new OrganizationContext($company->id, null));
        try {
            return $next($request);
        } finally {
            $request->attributes->remove(OrganizationContext::class);
        }
    }
}
