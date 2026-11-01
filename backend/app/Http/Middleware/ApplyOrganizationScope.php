<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Services\Auth\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ApplyOrganizationScope
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->query->has('scope_company_id') && ! $request->query->has('scope_branch_id')) {
            return $next($request);
        }

        $data = Validator::make($request->query(), [
            'scope_company_id' => ['required', 'integer', 'min:1'],
            'scope_branch_id' => ['nullable', 'integer', 'min:1'],
        ])->validate();
        $user = $request->user()->loadMissing('roles.permissions');
        $companyId = (int) $data['scope_company_id'];
        $branchId = isset($data['scope_branch_id']) ? (int) $data['scope_branch_id'] : null;
        if (! app(\App\Services\Tenancy\TenantDatabaseManager::class)->current()
            && ! \App\Services\Tenancy\SchemaLayout::legacyTestSchema()
            && $request->is('api/companies', 'api/billing/current')) {
            $branchId = null; // Directory and billing reads are company-wide.
        }

        // Feature flow step 1: validate selection before any route model is resolved.
        abort_unless($user->hasRole('super_admin') || $user->roles->contains(fn ($role) => $role->slug === 'company_admin'
            && (int) $role->pivot->company_id === $companyId
            && $role->pivot->scope === 'company'
        ), 403, 'You cannot select this company.');
        Company::query()->findOrFail($companyId);
        if ($branchId && (app(\App\Services\Tenancy\TenantDatabaseManager::class)->current() || \App\Services\Tenancy\SchemaLayout::legacyTestSchema())) {
            Branch::query()->where('company_id', $companyId)->findOrFail($branchId);
        } elseif ($branchId) {
            throw ValidationException::withMessages(['scope_branch_id' => ['Select a client HR context to filter by branch.']]);
        }

        $context = new OrganizationContext($companyId, $branchId);
        $request->attributes->set(OrganizationContext::class, $context);
        try {
            // Feature flow step 2: prevent writes and exports from escaping the visible scope.
            $this->validateWrites($request, $context);

            return $next($request);
        } finally {
            $request->attributes->remove(OrganizationContext::class);
        }
    }

    private function validateWrites(Request $request, OrganizationContext $context): void
    {
        if ($context->branchId && (
            (! $request->isMethodSafe() && $request->is('api/payroll-periods*', 'api/wps-payroll-batches*', 'api/wps-transfer-proofs*', 'api/compliance/emiratisation/snapshot'))
            || $request->is('api/wps-payroll-batches/*/download', 'api/wps-transfer-proofs/*/download')
        )) {
            throw ValidationException::withMessages(['scope_branch_id' => ['Select All branches for company-wide payroll, WPS actions and compliance snapshots.']]);
        }

        if ($request->isMethodSafe()) {
            return;
        }
        if ($context->branchId && $request->isMethod('POST') && $request->is('api/branches')) {
            throw ValidationException::withMessages(['scope_branch_id' => ['Select All branches to create a branch.']]);
        }
        if ($request->has('company_id') && (int) $request->input('company_id') !== $context->companyId) {
            throw ValidationException::withMessages(['company_id' => ['Company must match the selected company.']]);
        }
        if ($context->branchId && $request->filled('branch_id') && (int) $request->input('branch_id') !== $context->branchId) {
            throw ValidationException::withMessages(['branch_id' => ['Branch must match the selected branch.']]);
        }
        if ($request->filled('branch_id')) {
            $request->validate(['branch_id' => ['integer']]);
            if (! Branch::query()->whereKey($request->integer('branch_id'))->exists()) {
                throw ValidationException::withMessages(['branch_id' => ['Branch must belong to the selected company.']]);
            }
        }
        if ($context->branchId && $request->is('api/employees', 'api/employees/*') && $request->has('branch_id') && ! $request->filled('branch_id')) {
            $request->merge(['branch_id' => $context->branchId]);
        }
        if ($context->branchId && $request->isMethod('POST') && $request->is('api/employees', 'api/departments', 'api/mohre-establishments') && ! $request->filled('branch_id')) {
            $request->merge(['branch_id' => $context->branchId]);
        }
        foreach (['employee_id', 'manager_employee_id'] as $field) {
            if ($request->filled($field)) {
                $request->validate([$field => ['integer']]);
                if (! Employee::query()->whereKey($request->integer($field))->exists()) {
                    throw ValidationException::withMessages([$field => ['Employee must belong to the selected company and branch.']]);
                }
            }
        }
    }
}
