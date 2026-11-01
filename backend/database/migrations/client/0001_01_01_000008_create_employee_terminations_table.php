<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_terminations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('termination_date');
            $table->date('last_working_date');
            $table->string('termination_type')->default('company_initiated');
            $table->text('termination_reason')->nullable();
            $table->decimal('basic_salary', 12, 2);
            $table->decimal('unpaid_leave_days', 8, 2)->default(0);
            $table->decimal('gratuity_amount', 12, 2)->default(0);
            $table->decimal('leave_encashment_amount', 12, 2)->default(0);
            $table->decimal('notice_paid_amount', 12, 2)->default(0);
            $table->decimal('other_earnings_amount', 12, 2)->default(0);
            $table->decimal('deductions_amount', 12, 2)->default(0);
            $table->decimal('final_settlement_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('status')->default('draft');
            $table->json('calculation_snapshot_json');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_terminations');
    }
};
