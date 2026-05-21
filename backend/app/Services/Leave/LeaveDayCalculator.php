<?php

namespace App\Services\Leave;

use Carbon\CarbonImmutable;

class LeaveDayCalculator
{
    public function calculate(string $startDate, string $endDate): array
    {
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->startOfDay();
        $totalDays = $start->diffInDays($end) + 1;
        $workingDays = 0;

        // Feature flow step 1: MVP working days exclude Saturday and Sunday until public holidays are configured.
        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            if (! $date->isWeekend()) {
                $workingDays++;
            }
        }

        return [
            'total_days' => $totalDays,
            'working_days' => $workingDays,
        ];
    }
}
