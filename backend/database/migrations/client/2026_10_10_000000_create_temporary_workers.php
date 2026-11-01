<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temporary_workers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->string('name');
            $table->string('phone', 50)->nullable();
            $table->string('job_description')->nullable();
            $table->string('wage_basis', 10);
            $table->decimal('rate', 12, 2);
            $table->string('currency', 3);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->index(['company_id', 'branch_id', 'status']);
        });
        Schema::create('temporary_worker_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('temporary_worker_id')->constrained();
            $table->uuid('entry_key');
            $table->string('kind', 10);
            $table->date('date');
            $table->string('wage_basis', 10)->nullable();
            $table->decimal('quantity', 6, 2)->nullable();
            $table->decimal('rate', 12, 2)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('method', 20)->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->unique(['temporary_worker_id', 'entry_key']);
            $table->index(['temporary_worker_id', 'kind', 'date']);
        });
        // Add permissions without replacing existing role customizations.
        foreach (['view', 'manage', 'pay'] as $action) {
            $slug = 'temporary_workers.'.$action;
            DB::table('permissions')->insertOrIgnore(['slug' => $slug, 'name' => ucfirst($action).' temporary workers', 'module' => 'temporary_workers', 'created_at' => now(), 'updated_at' => now()]);
            $id = DB::table('permissions')->where('slug', $slug)->value('id');
            foreach (DB::table('roles')->whereNull('company_id')->whereIn('slug', ['super_admin', 'company_admin', 'payroll_manager'])->pluck('id') as $roleId) {
                DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('temporary_worker_entries');
        Schema::dropIfExists('temporary_workers');
        $ids = DB::table('permissions')->where('module', 'temporary_workers')->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
