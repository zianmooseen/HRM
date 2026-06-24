<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Models\Company;
use App\Models\PayrollPeriod;
use App\Services\Auth\CompanyAccess;
use App\Services\Payroll\WpsComplianceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WpsComplianceController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(
        private readonly CompanyAccess $access,
        private readonly WpsComplianceService $compliance,
    ) {
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, 'salary_transfers.view');
        $company = $this->access->ensureCompany($user);

        return $this->success('WPS compliance summary retrieved.', [
            'wps_compliance' => $this->companySummary($company),
        ]);
    }

    public function risk(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('roles.permissions');
        abort_unless($user->hasRole('super_admin'), 403, 'You are not authorized to perform this action.');

        return $this->success('Platform WPS payroll risk retrieved.', [
            'companies' => Company::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get()
                ->map(fn (Company $company) => $this->companySummary($company))
                ->all(),
        ]);
    }

    private function companySummary(Company $company): array
    {
        $periods = PayrollPeriod::query()
            ->where('company_id', $company->id)
            ->with(['wpsPayrollBatches' => fn ($query) => $query->latest('id'), 'mohreEstablishment', 'wpsProvider'])
            ->latest('period_start')
            ->limit(12)
            ->get();

        $rows = $periods->map(function (PayrollPeriod $period): array {
            $batch = $period->wpsPayrollBatches->first();

            return [
                'payroll_period_id' => $period->id,
                'period_start' => $period->period_start?->toDateString(),
                'period_end' => $period->period_end?->toDateString(),
                'payroll_status' => $period->status,
                'wps_status' => $batch?->status ?? $period->wps_status,
                'batch_id' => $batch?->id,
                'proof_status' => $batch?->proof_status,
                'provider_reference' => $batch?->provider_reference,
                'mohre_establishment' => $period->mohreEstablishment?->establishment_name,
                'wps_provider' => $period->wpsProvider?->name,
                ...$this->compliance->status($period, $batch),
            ];
        });

        return [
            'company_id' => $company->id,
            'company_name' => $company->name,
            'risk_status' => $rows->first()['status'] ?? 'not_scheduled',
            'open_alerts' => $company->wpsPayrollBatches()
                ->whereNotIn('status', ['paid', 'accepted', 'manual_override', 'cancelled'])
                ->count(),
            'periods' => $rows->all(),
        ];
    }
}
