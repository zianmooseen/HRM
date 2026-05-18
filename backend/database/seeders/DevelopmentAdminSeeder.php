<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Branch;
use App\Models\Department;
use App\Models\JobTitle;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevelopmentAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SEED_ADMIN_EMAIL');
        $password = env('SEED_ADMIN_PASSWORD');

        if (! $email || ! $password) {
            return;
        }

        // Feature flow step 1: create a local-only admin account from environment values.
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => env('SEED_ADMIN_NAME', 'Admin User'),
                'password' => Hash::make($password),
                'status' => 'active',
            ],
        );

        $role = Role::query()->where('slug', 'super_admin')->first();

        if ($role) {
            // Feature flow step 2: grant global access without tying the user to a company yet.
            $user->roles()->syncWithoutDetaching([
                $role->id => ['scope' => 'global'],
            ]);
        }

        $companyEmail = env('SEED_COMPANY_ADMIN_EMAIL');
        $companyPassword = env('SEED_COMPANY_ADMIN_PASSWORD');

        if (! $companyEmail || ! $companyPassword) {
            return;
        }

        // Feature flow step 3: create a sample company so company-admin access has a real tenant scope.
        $company = Company::query()->firstOrCreate(
            ['name' => env('SEED_COMPANY_NAME', 'Demo UAE Company')],
            [
                'legal_name' => env('SEED_COMPANY_LEGAL_NAME', 'Demo UAE Company LLC'),
                'country' => 'AE',
                'emirate' => 'Dubai',
                'default_currency' => 'AED',
                'timezone' => 'Asia/Dubai',
                'status' => 'active',
            ],
        );

        // Feature flow step 4: create a company-scoped admin account for tenant workflows.
        $companyUser = User::query()->updateOrCreate(
            ['email' => $companyEmail],
            [
                'name' => env('SEED_COMPANY_ADMIN_NAME', 'Company Admin'),
                'password' => Hash::make($companyPassword),
                'status' => 'active',
            ],
        );

        $companyAdminRole = Role::query()->where('slug', 'company_admin')->first();

        if ($companyAdminRole) {
            $companyUser->roles()->syncWithoutDetaching([
                $companyAdminRole->id => [
                    'company_id' => $company->id,
                    'scope' => 'company',
                ],
            ]);
        }

        // Feature flow step 5: seed setup data so employee creation is useful immediately.
        $branch = Branch::query()->firstOrCreate(
            ['company_id' => $company->id, 'code' => 'DXB-HQ'],
            [
                'name' => 'Dubai HQ',
                'emirate' => 'Dubai',
                'city' => 'Dubai',
                'status' => 'active',
                'created_by' => $companyUser->id,
                'updated_by' => $companyUser->id,
            ],
        );

        Department::query()->firstOrCreate(
            ['company_id' => $company->id, 'code' => 'HR'],
            [
                'branch_id' => $branch->id,
                'name' => 'Human Resources',
                'status' => 'active',
                'created_by' => $companyUser->id,
                'updated_by' => $companyUser->id,
            ],
        );

        JobTitle::query()->firstOrCreate(
            ['company_id' => $company->id, 'code' => 'HR-MGR'],
            [
                'title' => 'HR Manager',
                'description' => 'Manages company HR operations and employee lifecycle workflows.',
                'status' => 'active',
                'created_by' => $companyUser->id,
                'updated_by' => $companyUser->id,
            ],
        );
    }
}
