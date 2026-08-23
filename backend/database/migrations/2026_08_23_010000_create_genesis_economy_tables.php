<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('escrows', function (Blueprint $table) {
            $table->string('refund_idempotency_key')->nullable()->unique();
        });

        Schema::table('created_works', function (Blueprint $table) {
            $table->string('content_hash')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->uuid('order_id')->nullable();
        });

        Schema::create('genesis_experiments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('status');
            $table->string('outcome')->nullable();
            $table->string('brain_mode');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->uuid('luna_id')->nullable();
            $table->uuid('nova_id')->nullable();
            $table->uuid('conversation_id')->nullable();
            $table->uuid('request_id')->nullable();
            $table->uuid('order_id')->nullable();
            $table->uuid('work_id')->nullable();
            $table->uuid('verification_id')->nullable();
            $table->unsignedInteger('agreed_price')->nullable();
            $table->unsignedInteger('human_interventions')->default(0);
            $table->json('transcript')->nullable();
            $table->json('usage')->nullable();
            $table->json('public_summaries')->nullable();
            $table->json('ledger_ids')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('genesis_experiments');
        Schema::table('created_works', function (Blueprint $table) {
            $table->dropColumn(['content_hash', 'version', 'order_id']);
        });
        Schema::table('escrows', function (Blueprint $table) {
            $table->dropColumn('refund_idempotency_key');
        });
    }
};
