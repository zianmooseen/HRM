<?php

namespace App\Services\Payroll;

use App\Models\PayrollPeriod;
use Illuminate\Support\Collection;

class GenericSifExporter implements WpsFileExporter
{
    public function provider(): string
    {
        return 'generic';
    }

    public function generate(PayrollPeriod $period, Collection $rows): WpsFile
    {
        $lines = [];

        foreach ($rows as $row) {
            $lines[] = $this->line([
                'EDR',
                $row['wps_person_id'],
                $row['bank_routing_code'],
                $row['bank_iban'],
                $row['salary_month'],
                number_format($row['fixed_income'], 2, '.', ''),
                number_format($row['variable_income'], 2, '.', ''),
                $row['days_in_period'],
                $row['employee_code'],
            ]);
        }

        $lines[] = $this->line([
            'SCR',
            $period->company->mohre_establishment_number,
            $period->company->wps_agent_code,
            $period->company->wps_file_sender_id,
            $period->period_start->format('Ym'),
            $rows->count(),
            number_format($rows->sum('net_pay'), 2, '.', ''),
        ]);

        return new WpsFile('sif', 'sif', implode("\n", $lines)."\n");
    }

    protected function line(array $values): string
    {
        return collect($values)
            ->map(fn ($value) => str_replace(["\n", "\r", ','], ' ', (string) $value))
            ->implode(',');
    }
}
