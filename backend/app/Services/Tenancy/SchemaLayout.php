<?php

namespace App\Services\Tenancy;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class SchemaLayout
{
    public const PLATFORM_TABLES = ['companies', 'users', 'roles', 'permissions', 'role_permissions', 'user_roles', 'audit_logs', 'subscription_plans', 'company_subscriptions', 'billing_invoices', 'platform_clients', 'tenant_provisioning_runs', 'wps_providers', 'client_login_identifiers', 'migrations'];

    public const CLIENT_TABLES = [
        'branches', 'departments', 'job_titles', 'employees', 'employee_invitations',
        'onboarding_templates', 'onboarding_template_tasks', 'employee_onboarding_cases', 'employee_onboarding_tasks',
        'legal_rule_sets', 'legal_rule_items', 'company_compliance_settings', 'leave_types', 'leave_policies', 'leave_policy_rules',
        'employee_leave_balances', 'leave_requests', 'salary_components', 'employee_salary_components', 'payroll_periods',
        'payslips', 'payslip_items', 'leave_pay_calculation_items', 'emiratisation_rules', 'emiratisation_snapshots',
        'attendance_records', 'documents', 'leave_request_status_events', 'employee_terminations', 'employee_service_periods',
        'public_holidays', 'attendance_correction_requests', 'wps_payroll_batches', 'wps_payroll_batch_items',
        'wps_compliance_alerts', 'mohre_establishments', 'company_wps_settings', 'employee_government_profiles',
        'wps_transfer_proofs', 'temporary_workers', 'temporary_worker_entries',
    ];

    public static function legacyTestSchema(): bool
    {
        return app()->environment('testing') && config('tenancy.legacy_test_schema', false);
    }

    public static function removeLegacyTables(bool $platform): void
    {
        if (self::legacyTestSchema()) {
            return;
        }
        $connection = DB::connection();
        $schema = $connection->getSchemaBuilder();
        $tables = array_values(array_filter($platform ? self::CLIENT_TABLES : ['billing_invoices', 'company_subscriptions', 'subscription_plans'], fn ($table) => $schema->hasTable($table)));
        $referenceTables = ['legal_rule_sets', 'legal_rule_items', 'leave_types', 'emiratisation_rules', 'wps_providers'];
        foreach ($tables as $table) {
            if ($platform && $table === 'leave_types' && $connection->table($table)->whereNotNull('company_id')->exists()) {
                throw new RuntimeException('Central company-specific leave types must be migrated before cleanup.');
            }
            if ($connection->table($table)->exists() && ! ($platform && in_array($table, $referenceTables, true))) {
                throw new RuntimeException('Schema separation stopped: '.$table.' still contains records. Back up and explicitly migrate or remove legacy data before retrying.');
            }
        }
        if ($platform && $schema->hasTable('user_roles')) {
            if ($connection->table('user_roles')->whereNotNull('branch_id')->orWhereNotNull('department_id')->exists()) {
                throw new RuntimeException('Central role assignments still reference HR structures. Resolve them before schema cleanup.');
            }
            foreach ($schema->getForeignKeys('user_roles') as $key) {
                if (in_array($key['foreign_table'], $tables, true)) {
                    $schema->table('user_roles', fn ($blueprint) => $blueprint->dropForeign($connection->getDriverName() === 'sqlite' ? $key['columns'] : $key['name']));
                }
            }
        }
        // Drop children before parents without CASCADE, so unrelated constraints fail safely.
        while ($tables) {
            $ready = array_filter($tables, function ($candidate) use ($tables, $schema) {
                foreach ($tables as $other) {
                    if ($other === $candidate) {
                        continue;
                    }
                    foreach ($schema->getForeignKeys($other) as $key) {
                        if ($key['foreign_table'] === $candidate) {
                            return false;
                        }
                    }
                }

                return true;
            });
            if (! $ready) {
                // Legacy branches/departments and employees reference one another.
                // Only remove constraints wholly inside the already-validated deletion set.
                foreach ($tables as $table) {
                    foreach ($schema->getForeignKeys($table) as $key) {
                        if (in_array($key['foreign_table'], $tables, true)) {
                            $schema->table($table, fn ($blueprint) => $blueprint->dropForeign($connection->getDriverName() === 'sqlite' ? $key['columns'] : $key['name']));
                        }
                    }
                }
                $ready = $tables;
            }
            foreach ($ready as $table) {
                $schema->drop($table);
            }
            $tables = array_values(array_diff($tables, $ready));
        }
    }
}
