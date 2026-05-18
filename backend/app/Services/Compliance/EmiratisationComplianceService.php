<?php

namespace App\Services\Compliance;

class EmiratisationComplianceService
{
    public function calculate(array $company, array $employeeCounts, array $rule): array
    {
        if (($company['emiratisation_applicable'] ?? false) !== true) {
            return $this->snapshot($employeeCounts, 0, 0, 'not_applicable', $rule);
        }

        $category = $company['emiratisation_category'] ?? 'not_applicable';
        $totalSkilledWorkers = (int) ($employeeCounts['total_skilled_workers'] ?? 0);
        $totalSkilledCitizens = (int) ($employeeCounts['total_skilled_uae_citizens'] ?? 0);

        // Feature flow step 1: large companies are measured against skilled-worker growth obligations.
        if ($category === 'large_50_plus') {
            $growthPercent = (float) ($rule['annual_growth_percent'] ?? 2);
            $required = (int) ceil($totalSkilledWorkers * ($growthPercent / 100));

            return $this->result($employeeCounts, $required, $totalSkilledCitizens, $rule);
        }

        // Feature flow step 2: selected 20-49 companies use a minimum UAE citizen count.
        if ($category === 'selected_20_to_49') {
            $required = (int) ($rule['required_uae_citizens'] ?? 1);

            return $this->result($employeeCounts, $required, (int) ($employeeCounts['total_active_uae_citizens'] ?? 0), $rule);
        }

        return $this->snapshot($employeeCounts, 0, 0, 'not_applicable', $rule);
    }

    private function result(array $counts, int $required, int $actual, array $rule): array
    {
        $missing = max(0, $required - $actual);
        $status = $missing === 0 ? 'compliant' : 'non_compliant';

        return $this->snapshot($counts, $required, $missing, $status, $rule);
    }

    private function snapshot(array $counts, int $required, int $missing, string $status, array $rule): array
    {
        $contribution = $missing * (float) ($rule['contribution_amount_per_missing_citizen'] ?? 0);

        return [
            'total_active_workers' => (int) ($counts['total_active_workers'] ?? 0),
            'total_skilled_workers' => (int) ($counts['total_skilled_workers'] ?? 0),
            'total_active_uae_citizens' => (int) ($counts['total_active_uae_citizens'] ?? 0),
            'total_skilled_uae_citizens' => (int) ($counts['total_skilled_uae_citizens'] ?? 0),
            'required_uae_citizens' => $required,
            'missing_uae_citizens' => $missing,
            'estimated_contribution_amount' => $contribution,
            'compliance_status' => $status,
            'rule_snapshot_json' => $rule,
        ];
    }
}
