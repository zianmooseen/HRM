<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('wps_provider')->default('generic')->after('wps_bank_name');
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->string('work_permit_number')->nullable()->after('work_permit_type');
            $table->string('labor_card_number')->nullable()->after('work_permit_number');
        });

        Schema::table('wps_payroll_batches', function (Blueprint $table): void {
            $table->string('provider')->default('generic')->after('file_format');
            $table->string('bank_reference')->nullable()->after('rejection_reason');
            $table->string('response_filename')->nullable()->after('bank_reference');
            $table->json('response_details_json')->nullable()->after('response_filename');
            $table->foreignId('status_updated_by')->nullable()->after('generated_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wps_payroll_batches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('status_updated_by');
            $table->dropColumn(['provider', 'bank_reference', 'response_filename', 'response_details_json']);
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn(['work_permit_number', 'labor_card_number']);
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('wps_provider');
        });
    }
};
