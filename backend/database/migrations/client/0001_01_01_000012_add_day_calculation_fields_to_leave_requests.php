<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->decimal('public_holidays_count', 8, 2)->default(0)->after('working_days');
            $table->json('day_calculation_json')->nullable()->after('public_holidays_count');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->dropColumn(['public_holidays_count', 'day_calculation_json']);
        });
    }
};
