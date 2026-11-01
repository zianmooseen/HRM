<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_login_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id'); // Local ID in the client's database.
            $table->char('identifier_hash', 64)->index();
            $table->unique(['platform_client_id', 'user_id', 'identifier_hash'], 'client_login_identifier_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_login_identifiers');
    }
};
