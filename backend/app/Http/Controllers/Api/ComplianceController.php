<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Compliance\CalculateGratuityRequest;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\LegalRuleSetResource;
use App\Models\Employee;
use App\Services\Auth\CompanyAccess;
use App\Services\Compliance\GratuityCalculator;
use App\Services\Compliance\LegalRuleRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class ComplianceController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(
        private readonly CompanyAccess $access,
        private readonly LegalRuleRepository $rules,
        private readonly GratuityCalculator $gratuity,
    ) {
    }

    public function legalRules(Request $request): JsonResponse
    {
        $this->company($request);
        $ruleSet = $this->rules->activeRuleSet();

        return $this->success('Legal rules retrieved.', [
            'legal_rule_set' => $ruleSet ? new LegalRuleSetResource($ruleSet) : null,
        ]);
    }

    public function gratuity(CalculateGratuityRequest $request): JsonResponse
    {
        $company = $this->company($request);
        $data = $request->validated();
        $employee = Employee::query()
            ->whereKey($data['employee_id'])
            ->where('company_id', $company->id)
            ->first();

        abort_unless($employee, 422, 'Selected employee is invalid.');

        if (! $employee->hire_date) {
            throw ValidationException::withMessages(['employee_id' => ['Employee hire date is required for gratuity calculation.']]);
        }

        $basicSalary = (float) ($data['basic_salary'] ?? $employee->basic_salary ?? 0);

        if ($basicSalary <= 0) {
            throw ValidationException::withMessages(['basic_salary' => ['A positive basic salary is required for gratuity calculation.']]);
        }

        $ruleValues = $this->rules->values();
        $rules = [
            'minimum_service_years' => $ruleValues['gratuity.minimum_service_years'] ?? 1,
            'first_tier_years' => $ruleValues['gratuity.first_tier_years'] ?? 5,
            'first_tier_days_per_year' => $ruleValues['gratuity.first_tier_days_per_year'] ?? 21,
            'second_tier_days_per_year' => $ruleValues['gratuity.second_tier_days_per_year'] ?? 30,
            'daily_wage_divisor' => $ruleValues['gratuity.daily_wage_divisor'] ?? 30,
            'maximum_total_months' => $ruleValues['gratuity.maximum_total_months'] ?? 24,
            'currency' => $company->default_currency ?: 'AED',
        ];

        // Feature flow step 1: use employee record data, but allow HR to test salary/unpaid-leave scenarios.
        $result = $this->gratuity->calculate([
            'start_date' => $employee->hire_date->toDateString(),
            'end_date' => $data['termination_date'],
            'basic_salary' => $basicSalary,
            'unpaid_leave_days' => $data['unpaid_leave_days'] ?? 0,
        ], $rules);

        return $this->success('Gratuity calculated.', [
            'employee' => new EmployeeResource($employee),
            'gratuity' => $result,
        ]);
    }

    private function company(Request $request)
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, 'settings.view');

        return $this->access->ensureCompany($user);
    }
}
