<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LegalRuleSeeder extends Seeder
{
    public function run(): void
    {
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
        ];

        foreach ($rules as $key => $rule) {
            DB::table('legal_rule_items')->insert([
                'legal_rule_set_id' => $ruleSetId,
                'rule_key' => $key,
                'rule_type' => $rule['type'],
                'value_json' => json_encode(['value' => $rule['value']]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
