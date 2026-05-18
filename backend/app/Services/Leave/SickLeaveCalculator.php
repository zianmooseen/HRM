<?php

namespace App\Services\Leave;

use Carbon\CarbonInterface;

class SickLeaveCalculator
{
    public function calculate(array $input, array $rules): array
    {
        $requestedDays = (float) $input['requested_days'];
        $previouslyUsedDays = (float) ($input['previously_used_days'] ?? 0);
        $dailyWage = (float) $input['daily_wage'];
        $isInProbation = (bool) ($input['is_in_probation'] ?? false);
        $hasMedicalDocument = (bool) ($input['has_medical_document'] ?? false);
        $medicalDocumentRequired = (bool) ($rules['medical_document_required'] ?? true);

        if ($medicalDocumentRequired && ! $hasMedicalDocument) {
            return [
                'eligible' => false,
                'reason' => 'medical_document_required',
                'items' => [],
                'rule_snapshot' => $rules,
            ];
        }

        // Feature flow step 1: probation sick leave is unpaid unless company policy is more generous.
        if ($isInProbation && ! (bool) ($rules['paid_sick_leave_during_probation'] ?? false)) {
            return $this->singleTier('unpaid', $requestedDays, 0, $dailyWage, $rules);
        }

        // Feature flow step 2: split the request across UAE default sick leave pay tiers.
        $tiers = [
            ['key' => 'full_pay', 'limit' => (float) ($rules['full_pay_days'] ?? 15), 'percentage' => 100],
            ['key' => 'half_pay', 'limit' => (float) ($rules['half_pay_days'] ?? 30), 'percentage' => 50],
            ['key' => 'unpaid', 'limit' => (float) ($rules['unpaid_days'] ?? 45), 'percentage' => 0],
        ];

        $remaining = $requestedDays;
        $usedBeforeTier = $previouslyUsedDays;
        $items = [];

        foreach ($tiers as $tier) {
            if ($remaining <= 0) {
                break;
            }

            $availableInTier = max(0, $tier['limit'] - $usedBeforeTier);
            $days = min($remaining, $availableInTier);
            $usedBeforeTier = max(0, $usedBeforeTier - $tier['limit']);

            if ($days <= 0) {
                continue;
            }

            $remaining -= $days;
            $items[] = $this->item($tier['key'], $days, $tier['percentage'], $dailyWage, $rules);
        }

        if ($remaining > 0) {
            $items[] = $this->item('unpaid', $remaining, 0, $dailyWage, $rules);
        }

        return [
            'eligible' => true,
            'items' => $items,
            'rule_snapshot' => $rules,
        ];
    }

    private function singleTier(string $tier, float $days, int $percentage, float $dailyWage, array $rules): array
    {
        return [
            'eligible' => true,
            'items' => [$this->item($tier, $days, $percentage, $dailyWage, $rules)],
            'rule_snapshot' => $rules,
        ];
    }

    private function item(string $tier, float $days, int $percentage, float $dailyWage, array $rules): array
    {
        $grossPay = $days * $dailyWage * ($percentage / 100);

        return [
            'pay_tier' => $tier,
            'days' => $days,
            'pay_percentage' => $percentage,
            'daily_wage' => $dailyWage,
            'gross_pay_amount' => $grossPay,
            'deduction_amount' => $days * $dailyWage - $grossPay,
            'calculation_basis' => $rules['calculation_basis'] ?? 'daily_wage',
        ];
    }
}
