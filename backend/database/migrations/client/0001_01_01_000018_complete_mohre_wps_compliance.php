<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mohre_establishments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('establishment_name');
            $table->string('mohre_establishment_number');
            $table->string('labour_file_number')->nullable();
            $table->string('establishment_card_number')->nullable();
            $table->string('trade_license_number')->nullable();
            $table->string('emirate')->nullable();
            $table->string('status')->default('active');
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('wps_required')->default(true);
            $table->text('wps_exemption_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'mohre_establishment_number']);
        });

        Schema::create('wps_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('provider_type');
            $table->string('website')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('integration_type')->default('file_export');
            $table->string('export_profile')->default('generic');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('company_wps_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mohre_establishment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wps_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('payroll_due_day')->default(1);
            $table->string('salary_period_type')->default('monthly');
            $table->string('payment_currency', 3)->default('AED');
            $table->boolean('sif_export_enabled')->default(true);
            $table->string('provider_portal_url')->nullable();
            $table->string('provider_customer_reference')->nullable();
            $table->boolean('auto_mark_paid_allowed')->default(false);
            $table->string('agent_code')->nullable();
            $table->string('sender_id')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'mohre_establishment_id']);
        });

        Schema::create('employee_government_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('mohre_establishment_id')->nullable()->constrained()->nullOnDelete();
            $table->text('labour_card_number')->nullable();
            $table->text('work_permit_number')->nullable();
            $table->text('person_code')->nullable();
            $table->text('emirates_id_number')->nullable();
            $table->text('visa_file_number')->nullable();
            $table->text('passport_number')->nullable();
            $table->text('wps_employee_identifier')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('payroll_periods', function (Blueprint $table): void {
            $table->foreignId('mohre_establishment_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->foreignId('wps_provider_id')->nullable()->after('mohre_establishment_id')->constrained()->nullOnDelete();
            $table->date('payroll_due_date')->nullable()->after('pay_date');
            $table->string('wps_status')->default('not_started')->after('status');
            $table->timestamp('locked_at')->nullable()->after('approved_at');
            $table->foreignId('locked_by')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('wps_payroll_batches', function (Blueprint $table): void {
            $table->foreignId('mohre_establishment_id')->nullable()->after('payroll_period_id')->constrained()->nullOnDelete();
            $table->foreignId('wps_provider_id')->nullable()->after('mohre_establishment_id')->constrained()->nullOnDelete();
            $table->string('provider_reference')->nullable()->after('bank_reference');
            $table->string('file_hash', 64)->nullable()->after('file_content');
            $table->date('payroll_due_date')->nullable()->after('salary_month');
            $table->timestamp('paid_at')->nullable()->after('accepted_at');
            $table->text('failure_reason')->nullable()->after('rejection_reason');
            $table->text('manual_override_reason')->nullable()->after('failure_reason');
            $table->string('proof_status')->default('missing')->after('status');
        });

        Schema::table('wps_payroll_batch_items', function (Blueprint $table): void {
            $table->string('provider_employee_reference')->nullable()->after('wps_person_id');
            $table->string('provider_transaction_reference')->nullable()->after('provider_employee_reference');
            $table->timestamp('paid_at')->nullable()->after('status');
            $table->text('failure_reason')->nullable()->after('paid_at');
        });

        Schema::create('wps_transfer_proofs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wps_payroll_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wps_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('proof_type');
            $table->string('provider_reference')->nullable();
            $table->string('transaction_reference')->nullable();
            $table->string('disk')->nullable();
            $table->string('path')->nullable();
            $table->string('original_file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('proof_file_hash', 64)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('status')->default('uploaded');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wps_transfer_proofs');

        Schema::table('wps_payroll_batch_items', function (Blueprint $table): void {
            $table->dropColumn(['provider_employee_reference', 'provider_transaction_reference', 'paid_at', 'failure_reason']);
        });

        Schema::table('wps_payroll_batches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('mohre_establishment_id');
            $table->dropConstrainedForeignId('wps_provider_id');
            $table->dropColumn([
                'provider_reference',
                'file_hash',
                'payroll_due_date',
                'paid_at',
                'failure_reason',
                'manual_override_reason',
                'proof_status',
            ]);
        });

        Schema::table('payroll_periods', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('mohre_establishment_id');
            $table->dropConstrainedForeignId('wps_provider_id');
            $table->dropConstrainedForeignId('locked_by');
            $table->dropColumn(['payroll_due_date', 'wps_status', 'locked_at']);
        });

        Schema::dropIfExists('employee_government_profiles');
        Schema::dropIfExists('company_wps_settings');
        Schema::dropIfExists('wps_providers');
        Schema::dropIfExists('mohre_establishments');
    }
};
