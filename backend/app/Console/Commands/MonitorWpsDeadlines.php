<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\WpsComplianceAlert;
use App\Services\Payroll\WpsReadinessService;
use App\Services\Payroll\WpsComplianceService;
use Illuminate\Console\Command;

class MonitorWpsDeadlines extends Command
{
    protected $signature = 'wps:monitor-deadlines';

    protected $description = 'Create or resolve WPS payroll deadline alerts';

    public function handle(WpsReadinessService $readiness, WpsComplianceService $compliance): int
    {
        Company::query()->where('status', 'active')->each(function (Company $company) use ($readiness, $compliance): void {
            $period = $company->payrollPeriods()->latest('period_start')->latest('id')->first();

            if (! $period) {
                return;
            }

            $batch = $company->wpsPayrollBatches()->where('payroll_period_id', $period->id)->latest('id')->first();
            $status = $compliance->status($period, $batch);
            if (! $status['due_date']) {
                return;
            }

            $summary = $readiness->summary($company);
            $severity = $summary['compliance_status'];
            $alert = WpsComplianceAlert::query()->firstOrNew([
                'company_id' => $company->id,
                'payroll_period_id' => $period->id,
                'type' => 'payment_deadline',
            ]);

            if (in_array($severity, ['warning', 'urgent', 'overdue'], true)) {
                $alert->fill([
                    'severity' => $severity,
                    'message' => $this->message($severity, $summary['days_after_due']),
                    'due_date' => $summary['payment_due_date'],
                    'resolved_at' => null,
                ])->save();
            } elseif ($alert->exists && $alert->resolved_at === null) {
                $alert->update(['resolved_at' => now()]);
            }

            $proofAlert = WpsComplianceAlert::query()->firstOrNew([
                'company_id' => $company->id,
                'payroll_period_id' => $period->id,
                'type' => 'missing_transfer_proof',
            ]);

            if ($batch && in_array($batch->status, ['submitted', 'processing', 'paid'], true) && $batch->proof_status === 'missing') {
                $proofAlert->fill([
                    'severity' => $status['days_after_due'] >= 0 ? 'urgent' : 'warning',
                    'message' => 'WPS transfer proof or provider reference is missing for the latest payroll period.',
                    'due_date' => $status['due_date'],
                    'resolved_at' => null,
                ])->save();
            } elseif ($proofAlert->exists && $proofAlert->resolved_at === null) {
                $proofAlert->update(['resolved_at' => now()]);
            }
        });

        return self::SUCCESS;
    }

    private function message(string $severity, int $daysAfterDue): string
    {
        return match ($severity) {
            'overdue' => "WPS salary payment is {$daysAfterDue} days past its due date and needs immediate review.",
            'urgent' => "WPS salary payment is {$daysAfterDue} days past its due date and is approaching the 15-day limit.",
            default => "WPS salary payment is {$daysAfterDue} days past its due date. Confirm submission progress.",
        };
    }
}
