<?php

namespace App\Services\Compliance;

use Carbon\CarbonImmutable;

class GratuityCalculator
{
    public function calculate(array $input, array $rules): array
    {
        $start = CarbonImmutable::parse($input['start_date'])->startOfDay();
        $end = CarbonImmutable::parse($input['end_date'])->startOfDay();
        $basicSalary = (float) $input['basic_salary'];
        $unpaidLeaveDays = (float) ($input['unpaid_leave_days'] ?? 0);
        $dailyWage = $basicSalary / (float) ($rules['daily_wage_divisor'] ?? 30);
        $serviceDays = max(0, $start->diffInDays($end) + 1 - $unpaidLeaveDays);
        $serviceYears = $serviceDays / 365;

        if ($serviceYears < (float) ($rules['minimum_service_years'] ?? 1)) {
            return $this->result($serviceDays, $serviceYears, $dailyWage, 0, 0, 0, $rules);
        }

        $firstTierYears = min($serviceYears, (float) ($rules['first_tier_years'] ?? 5));
        $secondTierYears = max(0, $serviceYears - $firstTierYears);
        $firstTierDays = $firstTierYears * (float) ($rules['first_tier_days_per_year'] ?? 21);
        $secondTierDays = $secondTierYears * (float) ($rules['second_tier_days_per_year'] ?? 30);
        $totalGratuityDays = $firstTierDays + $secondTierDays;
        $amount = $totalGratuityDays * $dailyWage;
        $cap = $basicSalary * (float) ($rules['maximum_total_months'] ?? 24);

        // Feature flow step 1: UAE private-sector gratuity is capped at two years of wage.
        return $this->result($serviceDays, $serviceYears, $dailyWage, $totalGratuityDays, min($amount, $cap), $cap, $rules);
    }

    private function result(float $serviceDays, float $serviceYears, float $dailyWage, float $gratuityDays, float $amount, float $cap, array $rules): array
    {
        return [
            'service_days' => round($serviceDays, 2),
            'service_years' => round($serviceYears, 4),
            'daily_wage' => round($dailyWage, 2),
            'gratuity_days' => round($gratuityDays, 2),
            'gratuity_amount' => round($amount, 2),
            'maximum_amount' => round($cap, 2),
            'currency' => $rules['currency'] ?? 'AED',
            'rule_snapshot' => $rules,
        ];
    }
}
