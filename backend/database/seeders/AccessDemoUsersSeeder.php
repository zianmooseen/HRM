<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AccessDemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleAndPermissionSeeder::class);

        $company = Company::query()->firstOrCreate(
            ['name' => 'Demo UAE Company'],
            [
                'legal_name' => 'Demo UAE Company LLC',
                'country' => 'AE',
                'emirate' => 'Dubai',
                'default_currency' => 'AED',
                'timezone' => 'Asia/Dubai',
                'status' => 'active',
            ],
        );

        $superAdmin = $this->user('System Admin', 'sys.admin', 'sys.admin@example.local', 'sys.admin');
        $companyAdmin = $this->user('Company Admin', 'com.admin', 'com.admin@example.local', 'com.admin');
        $employeeUser = $this->user('Employee Demo', 'employee.demo', 'employee.demo@example.local', 'employee.demo');

        $this->assignRole($superAdmin, 'super_admin', null, 'global');
        $this->assignRole($companyAdmin, 'company_admin', $company, 'company');

        $branch = Branch::query()->firstOrCreate(
            ['company_id' => $company->id, 'code' => 'DXB-HQ'],
            [
                'name' => 'Dubai HQ',
                'emirate' => 'Dubai',
                'city' => 'Dubai',
                'status' => 'active',
                'created_by' => $companyAdmin->id,
                'updated_by' => $companyAdmin->id,
            ],
        );

        $department = Department::query()->firstOrCreate(
            ['company_id' => $company->id, 'code' => 'OPS'],
            [
                'branch_id' => $branch->id,
                'name' => 'Operations',
                'status' => 'active',
                'created_by' => $companyAdmin->id,
                'updated_by' => $companyAdmin->id,
            ],
        );

        $jobTitle = JobTitle::query()->firstOrCreate(
            ['company_id' => $company->id, 'code' => 'OPS-ASSOC'],
            [
                'title' => 'Operations Associate',
                'description' => 'Demo employee self-service role.',
                'status' => 'active',
                'created_by' => $companyAdmin->id,
                'updated_by' => $companyAdmin->id,
            ],
        );

        Employee::query()->updateOrCreate(
            ['company_id' => $company->id, 'employee_code' => 'EMP-DEMO'],
            [
                'branch_id' => $branch->id,
                'department_id' => $department->id,
                'job_title_id' => $jobTitle->id,
                'user_id' => $employeeUser->id,
                'first_name' => 'Employee',
                'last_name' => 'Demo',
                'display_name' => 'Employee Demo',
                'personal_email' => 'employee.demo.personal@example.local',
                'work_email' => 'employee.demo@example.local',
                'nationality' => 'United Arab Emirates',
                'is_uae_citizen' => true,
                'is_skilled_worker' => true,
                'hire_date' => now()->subYear()->toDateString(),
                'contract_start_date' => now()->subYear()->toDateString(),
                'contract_end_date' => now()->addYear()->toDateString(),
                'employment_type' => 'full_time',
                'contract_type' => 'fixed_term',
                'status' => 'active',
                'basic_salary' => 8000,
                'monthly_salary' => 12000,
                'created_by' => $companyAdmin->id,
                'updated_by' => $companyAdmin->id,
            ],
        );

        $this->assignRole($employeeUser, 'employee', $company, 'self');
    }

    private function user(string $name, string $username, string $email, string $password): User
    {
        return User::query()->updateOrCreate(
            ['username' => $username],
            [
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'status' => 'active',
            ],
        );
    }

    private function assignRole(User $user, string $roleSlug, ?Company $company, string $scope): void
    {
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

        $user->roles()->syncWithoutDetaching([
            $role->id => [
                'company_id' => $company?->id,
                'scope' => $scope,
            ],
        ]);
    }
}
