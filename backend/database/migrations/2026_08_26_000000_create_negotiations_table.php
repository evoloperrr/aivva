<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('negotiations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('request_id');
            $table->uuid('buyer_aivva_id');
            $table->uuid('seller_aivva_id');
            $table->string('state')->default('CONTACT_STARTED');
            // Whose turn it is to act next: 'buyer' or 'seller'. Null once terminal.
            $table->string('next_actor')->nullable();
            $table->unsignedInteger('active_offer_amount')->nullable();
            // Who is waiting on a response to their proposal: 'buyer' or 'seller'.
            $table->string('active_offer_by')->nullable();
            $table->unsignedInteger('turn_count')->default(0);
            $table->unsignedInteger('max_turns')->default(10);
            // Local usage/cost accounting for the live-test budget guard —
            // ai_provider_requests.conversation_id has its own FK to
            // aivva_conversations, so spend is tracked here instead.
            $table->unsignedInteger('spent_cost_cents')->default(0);
            $table->string('outcome')->nullable();
            $table->unsignedInteger('agreed_price')->nullable();
            // Immutable once set at AGREED — nothing after this point may rewrite it.
            $table->json('agreement')->nullable();
            $table->uuid('order_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('request_id')->references('id')->on('marketplace_requests')->cascadeOnDelete();
            $table->foreign('buyer_aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
            $table->foreign('seller_aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
            $table->index(['buyer_aivva_id', 'next_actor', 'state']);
            $table->index(['seller_aivva_id', 'next_actor', 'state']);
        });

        Schema::create('negotiation_turns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('negotiation_id');
            $table->uuid('actor_aivva_id');
            $table->string('role');
            $table->string('action');
            $table->unsignedInteger('price')->nullable();
            $table->text('message')->nullable();
            $table->text('reason_summary')->nullable();
            $table->string('state_before');
            $table->string('state_after');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->timestamps();

            $table->foreign('negotiation_id')->references('id')->on('negotiations')->cascadeOnDelete();
            $table->foreign('actor_aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('negotiation_turns');
        Schema::dropIfExists('negotiations');
    }
};
