<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('wps_bank_name')->nullable()->after('mohre_establishment_number');
            $table->string('wps_agent_code')->nullable()->after('wps_bank_name');
            $table->string('wps_file_sender_id')->nullable()->after('wps_agent_code');
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->string('bank_name')->nullable()->after('monthly_salary');
            $table->string('bank_iban')->nullable()->after('bank_name');
            $table->string('bank_routing_code')->nullable()->after('bank_iban');
            $table->string('wps_person_id')->nullable()->after('bank_routing_code');
        });

        Schema::create('wps_payroll_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number');
            $table->string('status')->default('generated');
            $table->string('file_format')->default('csv');
            $table->string('salary_month', 7);
            $table->unsignedInteger('record_count')->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('file_content')->nullable();
            $table->json('validation_errors_json')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'batch_number']);
            $table->unique(['company_id', 'payroll_period_id']);
        });

        Schema::create('wps_payroll_batch_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wps_payroll_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('employee_code');
            $table->string('employee_name');
            $table->string('bank_iban');
            $table->string('bank_routing_code');
            $table->string('wps_person_id');
            $table->decimal('fixed_income', 12, 2)->default(0);
            $table->decimal('variable_income', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);
            $table->unsignedInteger('days_in_period')->default(0);
            $table->json('row_payload_json');
            $table->string('status')->default('included');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wps_payroll_batch_items');
        Schema::dropIfExists('wps_payroll_batches');

        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn(['bank_name', 'bank_iban', 'bank_routing_code', 'wps_person_id']);
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['wps_bank_name', 'wps_agent_code', 'wps_file_sender_id']);
        });
    }
};
