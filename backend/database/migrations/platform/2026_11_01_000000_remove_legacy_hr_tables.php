<?php

use App\Services\Tenancy\SchemaLayout;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SchemaLayout::removeLegacyTables(platform: true);
    }

    public function down(): void
    {
        throw new RuntimeException('Schema cleanup is forward-only; restore a backup to reverse it.');
    }
};
