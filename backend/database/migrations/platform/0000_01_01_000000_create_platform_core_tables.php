<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Older installations already have these identity tables. Preserve their records.
        if (app()->environment('testing') && config('tenancy.legacy_test_schema')) {
            return;
        }
        if (! Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('legal_name')->nullable();
                $table->string('trade_license_number')->nullable();
                $table->string('tax_registration_number')->nullable();
                $table->string('country', 2)->default('AE');
                $table->string('emirate')->nullable();
                $table->string('default_currency', 3)->default('AED');
                $table->string('timezone')->default('Asia/Dubai');
                $table->string('status')->default('active');
                $table->boolean('emiratisation_applicable')->default(false);
                $table->string('emiratisation_category')->default('not_applicable');
                $table->string('economic_sector_code')->nullable();
                $table->string('mohre_establishment_number')->nullable();
                $table->string('wps_bank_name')->nullable();
                $table->string('wps_agent_code')->nullable();
                $table->string('wps_file_sender_id')->nullable();
                $table->string('wps_provider')->default('generic');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('username', 100)->nullable()->unique();
                $table->unsignedBigInteger('platform_user_id')->nullable()->unique();
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('phone')->nullable();
                $table->string('status')->default('active');
                $table->timestamp('last_login_at')->nullable();
                $table->rememberToken();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('slug');
                $table->text('description')->nullable();
                $table->boolean('is_system_role')->default(false);
                $table->timestamps();
                $table->unique(['company_id', 'slug']);
            });
        }

        if (! Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('module');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('role_permissions')) {
            Schema::create('role_permissions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('role_id')->constrained()->cascadeOnDelete();
                $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['role_id', 'permission_id']);
            });
        }

        if (! Schema::hasTable('user_roles')) {
            Schema::create('user_roles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->unsignedBigInteger('department_id')->nullable();
                $table->foreignId('role_id')->constrained()->cascadeOnDelete();
                $table->string('scope')->default('company');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('audit_logs')) {
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
        if (! Schema::hasTable('wps_providers')) {
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
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Platform identity migration is forward-only. Restore a backup instead of rolling it back.');
    }
};
