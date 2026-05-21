<?php

namespace App\Services\Compliance;

use App\Models\LegalRuleSet;

class LegalRuleRepository
{
    public function activeRuleSet(): ?LegalRuleSet
    {
        return LegalRuleSet::query()
            ->where('country_code', 'AE')
            ->where('status', 'active')
            ->with('items')
            ->orderByDesc('effective_from')
            ->first();
    }

    public function values(): array
    {
        $ruleSet = $this->activeRuleSet();

        if (! $ruleSet) {
            return [];
        }

        return $ruleSet->items
            ->mapWithKeys(fn ($item) => [$item->rule_key => $item->value_json['value'] ?? null])
            ->all();
    }
}
