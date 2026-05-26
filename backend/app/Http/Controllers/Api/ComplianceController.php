<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Compliance\CalculateGratuityRequest;
use App\Http\Requests\Compliance\UpdateCompanyComplianceSettingsRequest;
use App\Http\Resources\CompanyComplianceSettingResource;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\LegalRuleSetResource;
use App\Models\CompanyComplianceSetting;
use App\Models\Employee;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CompanyAccess;
use App\Services\Compliance\GratuityCalculator;
use App\Services\Compliance\LegalRuleRepository;
use Carbon\CarbonImmutable;
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
        private readonly AuditLogger $audit,
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

        $serviceStartDate = $this->serviceStartDate($employee);

        if (! $serviceStartDate) {
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
            'start_date' => $serviceStartDate->toDateString(),
            'end_date' => $data['termination_date'],
            'basic_salary' => $basicSalary,
            'unpaid_leave_days' => $data['unpaid_leave_days'] ?? 0,
        ], $rules);

        return $this->success('Gratuity calculated.', [
            'employee' => new EmployeeResource($employee),
            'gratuity' => $result,
        ]);
    }

    public function settings(Request $request): JsonResponse
    {
        $company = $this->company($request);
        $setting = $this->settingFor($company->id, $request->user()->id);

        return $this->success('Compliance settings retrieved.', [
            'compliance_settings' => new CompanyComplianceSettingResource($setting->load('legalRuleSet.items')),
        ]);
    }

    public function updateSettings(UpdateCompanyComplianceSettingsRequest $request): JsonResponse
    {
        $company = $this->company($request, 'settings.update');
        $setting = $this->settingFor($company->id, $request->user()->id);
        $before = $setting->toArray();

        // Feature flow step 1: compliance policy changes are centralized and audit logged for later review.
        $setting->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        $this->audit->log($request, 'company_compliance_settings.updated', $setting, $before, $setting->fresh()->toArray());

        return $this->success('Compliance settings updated.', [
            'compliance_settings' => new CompanyComplianceSettingResource($setting->fresh()->load('legalRuleSet.items')),
        ]);
    }

    private function serviceStartDate(Employee $employee): ?CarbonImmutable
    {
        $period = $employee->servicePeriods()
            ->whereIn('status', ['active', 'terminated'])
            ->latest('start_date')
            ->first();

        return $period?->start_date
            ? CarbonImmutable::parse($period->start_date)
            : ($employee->hire_date ? CarbonImmutable::parse($employee->hire_date) : null);
    }

    private function settingFor(int $companyId, int $userId): CompanyComplianceSetting
    {
        $ruleSet = $this->rules->activeRuleSet();

        abort_unless($ruleSet, 422, 'No active UAE legal rule set is configured.');

        return CompanyComplianceSetting::query()->firstOrCreate(
            ['company_id' => $companyId],
            [
                'legal_rule_set_id' => $ruleSet->id,
                'payroll_day_divisor' => 'calendar_30',
                'annual_leave_accrual_method' => 'monthly',
                'annual_leave_carry_forward_allowed' => true,
                'annual_leave_max_carry_forward_days' => null,
                'public_holidays_count_as_annual_leave' => false,
                'sick_leave_requires_medical_certificate' => true,
                'sick_leave_notification_days' => 3,
                'emiratisation_monitoring_enabled' => false,
                'created_by' => $userId,
                'updated_by' => $userId,
            ],
        );
    }

    private function company(Request $request, string $permission = 'settings.view')
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, $permission);

        return $this->access->ensureCompany($user);
    }
}
