<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect([
            ['name' => 'View companies', 'slug' => 'companies.view', 'module' => 'companies'],
            ['name' => 'Create companies', 'slug' => 'companies.create', 'module' => 'companies'],
            ['name' => 'Update companies', 'slug' => 'companies.update', 'module' => 'companies'],
            ['name' => 'View employees', 'slug' => 'employees.view', 'module' => 'employees'],
            ['name' => 'Create employees', 'slug' => 'employees.create', 'module' => 'employees'],
            ['name' => 'Update employees', 'slug' => 'employees.update', 'module' => 'employees'],
            ['name' => 'Delete employees', 'slug' => 'employees.delete', 'module' => 'employees'],
            ['name' => 'View employee salary', 'slug' => 'employees.view_salary', 'module' => 'employees'],
            ['name' => 'View attendance', 'slug' => 'attendance.view', 'module' => 'attendance'],
            ['name' => 'Create attendance', 'slug' => 'attendance.create', 'module' => 'attendance'],
            ['name' => 'Update attendance', 'slug' => 'attendance.update', 'module' => 'attendance'],
            ['name' => 'Approve attendance', 'slug' => 'attendance.approve', 'module' => 'attendance'],
            ['name' => 'View leave', 'slug' => 'leave.view', 'module' => 'leave'],
            ['name' => 'Create leave', 'slug' => 'leave.create', 'module' => 'leave'],
            ['name' => 'Approve leave', 'slug' => 'leave.approve', 'module' => 'leave'],
            ['name' => 'Reject leave', 'slug' => 'leave.reject', 'module' => 'leave'],
            ['name' => 'View payroll', 'slug' => 'payroll.view', 'module' => 'payroll'],
            ['name' => 'Run payroll', 'slug' => 'payroll.run', 'module' => 'payroll'],
            ['name' => 'Approve payroll', 'slug' => 'payroll.approve', 'module' => 'payroll'],
            ['name' => 'Export payroll', 'slug' => 'payroll.export', 'module' => 'payroll'],
            ['name' => 'View settings', 'slug' => 'settings.view', 'module' => 'settings'],
            ['name' => 'Update settings', 'slug' => 'settings.update', 'module' => 'settings'],
            ['name' => 'View audit logs', 'slug' => 'audit_logs.view', 'module' => 'audit_logs'],
        ])->mapWithKeys(fn ($permission) => [
            $permission['slug'] => Permission::query()->firstOrCreate(
                ['slug' => $permission['slug']],
                $permission
            ),
        ]);

        $roles = [
            'super_admin' => $permissions->keys()->all(),
            'company_admin' => $permissions->keys()->reject(fn ($slug) => $slug === 'companies.create')->values()->all(),
            'hr_manager' => ['employees.view', 'employees.create', 'employees.update', 'attendance.view', 'attendance.create', 'attendance.update', 'leave.view', 'leave.approve', 'leave.reject'],
            'payroll_manager' => ['employees.view', 'employees.view_salary', 'payroll.view', 'payroll.run', 'payroll.approve', 'payroll.export'],
            'department_manager' => ['employees.view', 'leave.view', 'leave.approve', 'leave.reject'],
            'employee' => ['leave.view', 'leave.create'],
        ];

        foreach ($roles as $slug => $rolePermissions) {
            $role = Role::query()->firstOrCreate(
                ['slug' => $slug, 'company_id' => null],
                ['name' => str($slug)->replace('_', ' ')->title(), 'is_system_role' => true]
            );

            $role->permissions()->sync($permissions->only($rolePermissions)->pluck('id')->all());
        }
    }
}
