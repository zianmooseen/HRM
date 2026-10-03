<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_clients', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('client_code', 50)->unique();
            $table->foreignId('company_id')->unique()->constrained()->restrictOnDelete();
            $table->string('database_name', 63)->unique();
            $table->string('provisioning_status')->default('pending');
            $table->text('setup_payload')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('maintenance_at')->nullable();
            $table->unsignedInteger('session_version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('tenant_provisioning_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_client_id')->constrained()->restrictOnDelete();
            $table->string('status');
            $table->string('step')->default('database');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('snapshot')->nullable();
            $table->foreignId('provisioned_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_provisioning_runs');
        Schema::dropIfExists('platform_clients');
    }
};
