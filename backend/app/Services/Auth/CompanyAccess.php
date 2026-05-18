<?php

namespace App\Services\Auth;

use App\Models\Company;
use App\Models\User;

class CompanyAccess
{
    public function currentCompany(User $user): ?Company
    {
        if ($user->hasRole('super_admin')) {
            return Company::query()->orderBy('id')->first();
        }

        return $user->currentCompany();
    }

    public function ensurePermission(User $user, string $permission): void
    {
        abort_unless($user->hasPermission($permission), 403, 'You are not authorized to perform this action.');
    }

    public function ensureCompany(User $user): Company
    {
        $company = $this->currentCompany($user);

        abort_unless($company, 403, 'No company scope is assigned to this user.');

        return $company;
    }
}
