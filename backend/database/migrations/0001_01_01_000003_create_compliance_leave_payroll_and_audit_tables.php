<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_rule_sets', function (Blueprint $table): void {
            $table->id();
            $table->string('country_code', 2)->default('AE');
            $table->string('jurisdiction');
            $table->string('name');
            $table->string('version');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('source_reference')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('legal_rule_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_rule_set_id')->constrained()->cascadeOnDelete();
            $table->string('rule_key');
            $table->string('rule_type');
            $table->json('value_json');
            $table->text('description')->nullable();
            $table->string('source_reference')->nullable();
            $table->timestamps();
            $table->unique(['legal_rule_set_id', 'rule_key']);
        });

        Schema::create('company_compliance_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_rule_set_id')->constrained()->restrictOnDelete();
            $table->string('payroll_day_divisor')->default('calendar_30');
            $table->string('annual_leave_accrual_method')->default('monthly');
            $table->boolean('annual_leave_carry_forward_allowed')->default(true);
            $table->decimal('annual_leave_max_carry_forward_days', 6, 2)->nullable();
            $table->boolean('public_holidays_count_as_annual_leave')->default(false);
            $table->boolean('sick_leave_requires_medical_certificate')->default(true);
            $table->unsignedInteger('sick_leave_notification_days')->default(3);
            $table->boolean('emiratisation_monitoring_enabled')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('leave_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('category');
            $table->string('paid_type');
            $table->boolean('requires_approval')->default(true);
            $table->boolean('requires_document')->default(false);
            $table->boolean('is_statutory')->default(false);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('leave_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_rule_set_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('applies_to_employment_type')->nullable();
            $table->foreignId('applies_to_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('applies_to_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('leave_policy_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('leave_policy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->string('rule_key');
            $table->json('value_json');
            $table->json('minimum_legal_value_json')->nullable();
            $table->string('validation_behavior')->default('reject_below_minimum');
            $table->timestamps();
        });

        Schema::create('employee_leave_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('leave_year');
            $table->decimal('opening_balance', 8, 2)->default(0);
            $table->decimal('accrued_days', 8, 2)->default(0);
            $table->decimal('used_days', 8, 2)->default(0);
            $table->decimal('pending_days', 8, 2)->default(0);
            $table->decimal('carried_forward_days', 8, 2)->default(0);
            $table->decimal('encashed_days', 8, 2)->default(0);
            $table->decimal('adjusted_days', 8, 2)->default(0);
            $table->decimal('closing_balance', 8, 2)->default(0);
            $table->timestamps();
            $table->unique(['employee_id', 'leave_type_id', 'leave_year']);
        });

        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 8, 2);
            $table->decimal('working_days', 8, 2);
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('medical_certificate_document_id')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('salary_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('type');
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_recurring')->default(true);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('employee_salary_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_component_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payroll_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('pay_date')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payslips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('gross_pay', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);
            $table->string('status')->default('draft');
            $table->json('calculation_snapshot_json')->nullable();
            $table->timestamps();
            $table->unique(['payroll_period_id', 'employee_id']);
        });

        Schema::create('payslip_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_component_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->string('type');
            $table->decimal('amount', 12, 2);
            $table->json('metadata_json')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_pay_calculation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->string('pay_tier');
            $table->decimal('days', 8, 2);
            $table->decimal('pay_percentage', 5, 2);
            $table->decimal('daily_wage', 12, 2);
            $table->decimal('gross_pay_amount', 12, 2);
            $table->decimal('deduction_amount', 12, 2);
            $table->string('calculation_basis');
            $table->json('rule_snapshot_json');
            $table->timestamps();
        });

        Schema::create('emiratisation_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_rule_set_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->unsignedInteger('min_employee_count')->nullable();
            $table->unsignedInteger('max_employee_count')->nullable();
            $table->json('sector_codes_json')->nullable();
            $table->decimal('annual_growth_percent', 5, 2)->nullable();
            $table->decimal('semi_annual_growth_percent', 5, 2)->nullable();
            $table->unsignedInteger('required_uae_citizens')->nullable();
            $table->decimal('contribution_amount_per_missing_citizen', 12, 2)->default(0);
            $table->string('contribution_frequency')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('emiratisation_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->unsignedInteger('total_active_workers')->default(0);
            $table->unsignedInteger('total_skilled_workers')->default(0);
            $table->unsignedInteger('total_active_uae_citizens')->default(0);
            $table->unsignedInteger('total_skilled_uae_citizens')->default(0);
            $table->unsignedInteger('required_uae_citizens')->default(0);
            $table->unsignedInteger('missing_uae_citizens')->default(0);
            $table->decimal('estimated_contribution_amount', 12, 2)->default(0);
            $table->string('compliance_status')->default('needs_review');
            $table->json('rule_snapshot_json')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('emiratisation_snapshots');
        Schema::dropIfExists('emiratisation_rules');
        Schema::dropIfExists('leave_pay_calculation_items');
        Schema::dropIfExists('payslip_items');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('employee_salary_components');
        Schema::dropIfExists('salary_components');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('employee_leave_balances');
        Schema::dropIfExists('leave_policy_rules');
        Schema::dropIfExists('leave_policies');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('company_compliance_settings');
        Schema::dropIfExists('legal_rule_items');
        Schema::dropIfExists('legal_rule_sets');
    }
};
