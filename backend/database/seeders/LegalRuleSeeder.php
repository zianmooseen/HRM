<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LegalRuleSeeder extends Seeder
{
    public function run(): void
    {
        $ruleSet = DB::table('legal_rule_sets')
            ->where('country_code', 'AE')
            ->where('jurisdiction', 'UAE_PRIVATE_SECTOR')
            ->where('version', '2026.1')
            ->first();

        if (! $ruleSet) {
            $ruleSetId = DB::table('legal_rule_sets')->insertGetId([
                'country_code' => 'AE',
                'jurisdiction' => 'UAE_PRIVATE_SECTOR',
                'name' => 'UAE Labour Law Private Sector Rules',
                'version' => '2026.1',
                'effective_from' => '2026-01-01',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $ruleSetId = $ruleSet->id;
        }

        $rules = [
            'annual_leave.full_year_days' => ['value' => 30, 'type' => 'integer'],
            'annual_leave.after_6_months_days_per_month' => ['value' => 2, 'type' => 'integer'],
            'sick_leave.max_days_per_year' => ['value' => 90, 'type' => 'integer'],
            'sick_leave.full_pay_days' => ['value' => 15, 'type' => 'integer'],
            'sick_leave.half_pay_days' => ['value' => 30, 'type' => 'integer'],
            'sick_leave.unpaid_days' => ['value' => 45, 'type' => 'integer'],
            'sick_leave.medical_report_required' => ['value' => true, 'type' => 'boolean'],
            'sick_leave.notification_days' => ['value' => 3, 'type' => 'integer'],
            'maternity_leave.full_pay_days' => ['value' => 45, 'type' => 'integer'],
            'maternity_leave.half_pay_days' => ['value' => 15, 'type' => 'integer'],
            'emiratisation.large_company_annual_growth_percent' => ['value' => 2, 'type' => 'decimal'],
            'emiratisation.selected_20_to_49_required_uae_citizens' => ['value' => 1, 'type' => 'integer'],
            'emiratisation.contribution_amount_per_missing_citizen' => ['value' => 96000, 'type' => 'decimal'],
            'gratuity.minimum_service_years' => ['value' => 1, 'type' => 'decimal'],
            'gratuity.first_tier_years' => ['value' => 5, 'type' => 'decimal'],
            'gratuity.first_tier_days_per_year' => ['value' => 21, 'type' => 'decimal'],
            'gratuity.second_tier_days_per_year' => ['value' => 30, 'type' => 'decimal'],
            'gratuity.daily_wage_divisor' => ['value' => 30, 'type' => 'decimal'],
            'gratuity.maximum_total_months' => ['value' => 24, 'type' => 'decimal'],
        ];

        foreach ($rules as $key => $rule) {
            DB::table('legal_rule_items')->updateOrInsert(
                ['legal_rule_set_id' => $ruleSetId, 'rule_key' => $key],
                [
                    'rule_type' => $rule['type'],
                    'value_json' => json_encode(['value' => $rule['value']]),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        foreach ([
            [
                'category' => 'large_50_plus',
                'min_employee_count' => 50,
                'max_employee_count' => null,
                'annual_growth_percent' => 2,
                'semi_annual_growth_percent' => 1,
                'required_uae_citizens' => null,
            ],
            [
                'category' => 'selected_20_to_49',
                'min_employee_count' => 20,
                'max_employee_count' => 49,
                'annual_growth_percent' => null,
                'semi_annual_growth_percent' => null,
                'required_uae_citizens' => 1,
            ],
        ] as $rule) {
            DB::table('emiratisation_rules')->updateOrInsert(
                [
                    'legal_rule_set_id' => $ruleSetId,
                    'category' => $rule['category'],
                    'effective_from' => '2026-01-01',
                ],
                [
                    'min_employee_count' => $rule['min_employee_count'],
                    'max_employee_count' => $rule['max_employee_count'],
                    'sector_codes_json' => null,
                    'annual_growth_percent' => $rule['annual_growth_percent'],
                    'semi_annual_growth_percent' => $rule['semi_annual_growth_percent'],
                    'required_uae_citizens' => $rule['required_uae_citizens'],
                    'contribution_amount_per_missing_citizen' => 96000,
                    'contribution_frequency' => 'annual',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
}
