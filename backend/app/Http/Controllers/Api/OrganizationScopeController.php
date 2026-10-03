<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OrganizationScopeController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __invoke(Request $request)
    {
        $user = $request->user()->loadMissing('roles');
        abort_unless($user->hasRole('super_admin') || $user->hasRole('company_admin'), 403);
        $companyIds = $user->roles->filter(fn ($role) => $role->slug === 'company_admin' && $role->pivot->scope === 'company')->pluck('pivot.company_id');

        $companies = Company::query()
            ->when(! $user->hasRole('super_admin'), fn ($query) => $query->whereIn('id', $companyIds))
            ->with(['branches' => fn ($query) => $query->orderBy('name')->select(['id', 'company_id', 'name', 'code'])])
            ->orderBy('name')->get(['id', 'name']);

        return $this->success('Organization filter options retrieved.', ['companies' => $companies]);
    }
}
