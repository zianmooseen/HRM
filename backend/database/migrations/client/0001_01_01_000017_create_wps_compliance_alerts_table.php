<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wps_compliance_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('payment_deadline');
            $table->string('severity');
            $table->text('message');
            $table->date('due_date');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'payroll_period_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wps_compliance_alerts');
    }
};
