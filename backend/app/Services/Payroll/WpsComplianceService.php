<?php

namespace App\Services\Payroll;

use App\Models\CompanyWpsSetting;
use App\Models\PayrollPeriod;
use App\Models\WpsPayrollBatch;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class WpsComplianceService
{
    private const TRANSITIONS = [
        'draft' => ['generated', 'cancelled'],
        'generated' => ['submitted', 'cancelled'],
        'submitted' => ['processing', 'accepted', 'partially_accepted', 'rejected', 'failed'],
        'processing' => ['accepted', 'partially_accepted', 'rejected', 'paid', 'failed'],
        'accepted' => ['paid', 'partially_paid', 'needs_review'],
        'partially_accepted' => ['processing', 'partially_paid', 'failed', 'needs_review'],
        'rejected' => ['corrected', 'cancelled'],
        'corrected' => ['generated', 'submitted', 'cancelled'],
        'partially_paid' => ['paid', 'failed', 'needs_review'],
        'failed' => ['corrected', 'needs_review', 'cancelled'],
        'needs_review' => ['processing', 'paid', 'failed', 'manual_override'],
        'manual_override' => [],
        'paid' => [],
        'cancelled' => [],
    ];

    public function dueDate(PayrollPeriod $period, ?CompanyWpsSetting $setting = null): ?CarbonImmutable
    {
        if ($period->payroll_due_date) {
            return CarbonImmutable::parse($period->payroll_due_date);
        }

        if ($period->pay_date) {
            return CarbonImmutable::parse($period->pay_date);
        }

        $setting ??= CompanyWpsSetting::query()
            ->where('company_id', $period->company_id)
            ->when($period->mohre_establishment_id, fn ($query) => $query->where('mohre_establishment_id', $period->mohre_establishment_id))
            ->where('status', 'active')
            ->first();

        if (! $setting) {
            return null;
        }

        return CarbonImmutable::parse($period->period_end)
            ->addMonthNoOverflow()
            ->day(min($setting->payroll_due_day, 28));
    }

    public function transition(WpsPayrollBatch $batch, string $nextStatus, ?string $overrideReason = null): array
    {
        if ($nextStatus === 'manual_override') {
            if (blank($overrideReason)) {
                throw ValidationException::withMessages([
                    'manual_override_reason' => ['A reason is required for a manual WPS status override.'],
                ]);
            }

            return $this->timestamps($batch, $nextStatus, $overrideReason);
        }

        $allowed = self::TRANSITIONS[$batch->status] ?? [];
        if (! in_array($nextStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => ["WPS batch cannot move from {$batch->status} to {$nextStatus}."],
            ]);
        }

        return $this->timestamps($batch, $nextStatus, null);
    }

    public function status(PayrollPeriod $period, ?WpsPayrollBatch $batch, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $dueDate = $this->dueDate($period);

        if (! $dueDate) {
            return ['status' => 'not_scheduled', 'due_date' => null, 'days_after_due' => null, 'proof_missing' => false];
        }

        $confirmed = $batch && in_array($batch->status, ['accepted', 'paid', 'manual_override'], true);
        $daysAfterDue = $dueDate->diffInDays($today, false);

        $status = match (true) {
            $confirmed => 'compliant',
            $daysAfterDue > 15 => 'overdue',
            $daysAfterDue >= 10 => 'urgent',
            $daysAfterDue >= 3 => 'warning',
            $daysAfterDue >= 0 => 'due',
            default => 'upcoming',
        };

        return [
            'status' => $status,
            'due_date' => $dueDate->toDateString(),
            'days_after_due' => $daysAfterDue,
            'proof_missing' => (bool) $batch && $batch->proof_status === 'missing',
        ];
    }

    private function timestamps(WpsPayrollBatch $batch, string $status, ?string $overrideReason): array
    {
        return [
            'status' => $status,
            'submitted_at' => $status === 'submitted' ? now() : $batch->submitted_at,
            'accepted_at' => in_array($status, ['accepted', 'partially_accepted'], true) ? now() : $batch->accepted_at,
            'paid_at' => in_array($status, ['paid', 'manual_override'], true) ? now() : $batch->paid_at,
            'rejected_at' => $status === 'rejected' ? now() : $batch->rejected_at,
            'manual_override_reason' => $overrideReason,
        ];
    }
}
