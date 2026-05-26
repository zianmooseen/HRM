<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Employee\CreateEmployeeAccountRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CompanyAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeAccountController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(private readonly CompanyAccess $access, private readonly AuditLogger $audit)
    {
    }

    public function store(CreateEmployeeAccountRequest $request, Employee $employee): JsonResponse
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, 'employees.update');
        $company = $this->access->ensureCompany($user);
        abort_unless($employee->company_id === $company->id, 403, 'You are not authorized to perform this action.');

        if ($employee->user_id) {
            throw ValidationException::withMessages(['employee_id' => ['Employee already has a login account.']]);
        }

        $data = $request->validated();
        $account = DB::transaction(function () use ($request, $employee, $company, $data): User {
            $account = User::query()->create([
                'name' => ($data['name'] ?? null) ?: $employee->display_name,
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => $data['password'],
                'phone' => $employee->phone,
                'status' => 'active',
            ]);
            $role = Role::query()->where('slug', 'employee')->whereNull('company_id')->firstOrFail();

            $account->roles()->attach($role->id, [
                'company_id' => $company->id,
                'branch_id' => $employee->branch_id,
                'department_id' => $employee->department_id,
                'scope' => 'self',
                'created_by' => $request->user()->id,
            ]);

            $before = $employee->toArray();
            $employee->update(['user_id' => $account->id, 'updated_by' => $request->user()->id]);
            $this->audit->log($request, 'employee.account_created', $employee, $before, $employee->fresh()->toArray());

            return $account;
        });

        return $this->success('Employee account created.', [
            'employee' => new EmployeeResource($employee->fresh()->load(['branch', 'department', 'jobTitle'])),
            'user' => [
                'id' => $account->id,
                'name' => $account->name,
                'username' => $account->username,
                'email' => $account->email,
            ],
        ], 201);
    }
}
