<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Models\AuditLog;
use App\Models\CompanyComplianceSetting;
use App\Models\EmiratisationSnapshot;
use App\Models\PublicHoliday;
use App\Services\Auth\CompanyAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComplianceReportController extends Controller
{
    use RespondsWithApiEnvelope;

    private const EXPORT_TYPES = ['settings', 'public_holidays', 'emiratisation', 'audit'];

    public function __construct(private readonly CompanyAccess $access)
    {
    }

    public function summary(Request $request): JsonResponse
    {
        $company = $this->company($request);
        $settings = CompanyComplianceSetting::query()
            ->where('company_id', $company->id)
            ->latest('id')
            ->first();
        $latestSnapshot = EmiratisationSnapshot::query()
            ->where('company_id', $company->id)
            ->latest('snapshot_date')
            ->latest('id')
            ->first();
        $activeHolidayCount = PublicHoliday::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->count();

        return $this->success('Compliance report summary retrieved.', [
            'summary' => [
                'company' => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'emiratisation_applicable' => (bool) $company->emiratisation_applicable,
                    'emiratisation_category' => $company->emiratisation_category,
                    'economic_sector_code' => $company->economic_sector_code,
                    'mohre_establishment_number' => $company->mohre_establishment_number,
                ],
                'settings' => $settings ? $this->settingsRow($settings) : null,
                'public_holiday_count' => $activeHolidayCount,
                'latest_emiratisation_snapshot' => $latestSnapshot ? $this->snapshotRow($latestSnapshot) : null,
                'recent_audit_logs' => $this->auditRows($company->id, 8),
                'exports' => self::EXPORT_TYPES,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $company = $this->company($request);
        $type = (string) $request->query('type', 'settings');

        abort_unless(in_array($type, self::EXPORT_TYPES, true), 422, 'Unsupported compliance report type.');

        $rows = $this->exportRows($company->id, $type);
        $headers = array_keys($rows[0] ?? ['message' => 'No records found.']);
        $filename = 'compliance-'.$type.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, array_map(
                    fn ($header) => $this->csvValue($row[$header] ?? ''),
                    $headers,
                ));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function exportRows(int $companyId, string $type): array
    {
        return match ($type) {
            'public_holidays' => PublicHoliday::query()
                ->where('company_id', $companyId)
                ->orderBy('holiday_date')
                ->get()
                ->map(fn (PublicHoliday $holiday) => [
                    'date' => $holiday->holiday_date?->toDateString(),
                    'name' => $holiday->name,
                    'country_code' => $holiday->country_code,
                    'emirate' => $holiday->emirate ?: 'All',
                    'paid' => $holiday->paid ? 'yes' : 'no',
                    'source' => $holiday->source,
                    'status' => $holiday->status,
                ])->all(),
            'emiratisation' => EmiratisationSnapshot::query()
                ->where('company_id', $companyId)
                ->orderByDesc('snapshot_date')
                ->orderByDesc('id')
                ->get()
                ->map(fn (EmiratisationSnapshot $snapshot) => $this->snapshotRow($snapshot))
                ->all(),
            'audit' => $this->auditRows($companyId, 200),
            default => CompanyComplianceSetting::query()
                ->where('company_id', $companyId)
                ->latest('id')
                ->get()
                ->map(fn (CompanyComplianceSetting $settings) => $this->settingsRow($settings))
                ->all(),
        };
    }

    private function settingsRow(CompanyComplianceSetting $settings): array
    {
        return [
            'payroll_day_divisor' => $settings->payroll_day_divisor,
            'annual_leave_accrual_method' => $settings->annual_leave_accrual_method,
            'annual_leave_carry_forward_allowed' => $settings->annual_leave_carry_forward_allowed ? 'yes' : 'no',
            'annual_leave_max_carry_forward_days' => $settings->annual_leave_max_carry_forward_days,
            'public_holidays_count_as_annual_leave' => $settings->public_holidays_count_as_annual_leave ? 'yes' : 'no',
            'sick_leave_requires_medical_certificate' => $settings->sick_leave_requires_medical_certificate ? 'yes' : 'no',
            'sick_leave_notification_days' => $settings->sick_leave_notification_days,
            'emiratisation_monitoring_enabled' => $settings->emiratisation_monitoring_enabled ? 'yes' : 'no',
            'updated_at' => optional($settings->updated_at)->toDateTimeString(),
        ];
    }

    private function snapshotRow(EmiratisationSnapshot $snapshot): array
    {
        return [
            'snapshot_date' => $snapshot->snapshot_date?->toDateString(),
            'total_active_workers' => $snapshot->total_active_workers,
            'total_skilled_workers' => $snapshot->total_skilled_workers,
            'total_active_uae_citizens' => $snapshot->total_active_uae_citizens,
            'total_skilled_uae_citizens' => $snapshot->total_skilled_uae_citizens,
            'required_uae_citizens' => $snapshot->required_uae_citizens,
            'missing_uae_citizens' => $snapshot->missing_uae_citizens,
            'estimated_contribution_amount' => $snapshot->estimated_contribution_amount,
            'compliance_status' => $snapshot->compliance_status,
        ];
    }

    private function auditRows(int $companyId, int $limit): array
    {
        return AuditLog::query()
            ->where('company_id', $companyId)
            ->where(function ($query): void {
                $query->where('action', 'like', 'company_compliance_settings.%')
                    ->orWhere('action', 'like', 'public_holiday.%')
                    ->orWhere('action', 'like', 'emiratisation_snapshot.%')
                    ->orWhere('action', 'like', 'leave_balance.%')
                    ->orWhere('action', 'like', 'leave_request.%')
                    ->orWhere('action', 'like', 'payroll_period.%');
            })
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log) => [
                'created_at' => optional($log->created_at)->toDateTimeString(),
                'action' => $log->action,
                'auditable_type' => class_basename($log->auditable_type),
                'auditable_id' => $log->auditable_id,
                'actor_user_id' => $log->actor_user_id,
            ])
            ->all();
    }

    private function csvValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        return (string) $value;
    }

    private function company(Request $request)
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, 'settings.view');

        return $this->access->ensureCompany($user);
    }
}
