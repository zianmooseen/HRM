<?php

namespace App\Services\Leave;

use App\Models\Company;
use App\Models\CompanyComplianceSetting;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\PublicHoliday;
use Carbon\CarbonImmutable;

class LeaveDayCalculator
{
    public function calculate(string $startDate, string $endDate, ?Company $company = null, ?Employee $employee = null, ?LeaveType $leaveType = null): array
    {
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->startOfDay();
        $totalDays = $start->diffInDays($end) + 1;
        $workingDays = 0;
        $holidayDates = $company && $leaveType
            ? $this->excludedHolidayDates($company, $employee, $leaveType, $start, $end)
            : [];
        $excludedHolidays = [];

        // Feature flow step 1: calculate balance-impact days by excluding weekends and, when policy requires it, public holidays.
        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            if ($date->isWeekend()) {
                continue;
            }

            $dateKey = $date->toDateString();

            if (isset($holidayDates[$dateKey])) {
                $excludedHolidays[] = [
                    'date' => $dateKey,
                    'name' => $holidayDates[$dateKey],
                ];

                continue;
            }

            $workingDays++;
        }

        return [
            'total_days' => $totalDays,
            'working_days' => $workingDays,
            'public_holidays_count' => count($excludedHolidays),
            'day_calculation_json' => [
                'weekend_days_excluded' => true,
                'public_holidays_count_as_annual_leave' => $company
                    ? $this->publicHolidaysCountAsAnnualLeave($company)
                    : null,
                'excluded_public_holidays' => $excludedHolidays,
            ],
        ];
    }

    private function excludedHolidayDates(Company $company, ?Employee $employee, LeaveType $leaveType, CarbonImmutable $start, CarbonImmutable $end): array
    {
        if ($leaveType->code !== 'annual_leave' || $this->publicHolidaysCountAsAnnualLeave($company)) {
            return [];
        }

        $employee?->loadMissing('branch');
        $emirate = $employee?->branch?->emirate;

        return PublicHoliday::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->where('paid', true)
            ->whereBetween('holiday_date', [$start->toDateString(), $end->toDateString()])
            ->where(function ($query) use ($emirate): void {
                $query->whereNull('emirate')->orWhere('emirate', '');

                if ($emirate) {
                    $query->orWhere('emirate', $emirate);
                }
            })
            ->orderBy('holiday_date')
            ->get()
            ->mapWithKeys(fn (PublicHoliday $holiday) => [
                $holiday->holiday_date->toDateString() => $holiday->name,
            ])
            ->all();
    }

    private function publicHolidaysCountAsAnnualLeave(Company $company): bool
    {
        return (bool) CompanyComplianceSetting::query()
            ->where('company_id', $company->id)
            ->value('public_holidays_count_as_annual_leave');
    }
}
