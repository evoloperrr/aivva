<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xentoz_identity_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('xentoz_user_id')->unique();
            $table->timestamp('linked_at');
            $table->timestamp('last_asserted_at');
            $table->timestamps();
        });

        Schema::create('xentoz_integration_nonces', function (Blueprint $table) {
            $table->string('nonce', 64)->primary();
            $table->timestamp('expires_at')->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xentoz_integration_nonces');
        Schema::dropIfExists('xentoz_identity_links');
    }
};
