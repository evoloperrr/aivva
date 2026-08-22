<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('owner_type');
            $table->string('owner_id');
            $table->string('currency')->default('AIVVA_CREDITS');
            $table->unsignedBigInteger('available_balance')->default(0);
            $table->unsignedBigInteger('held_balance')->default(0);
            $table->timestamps();
            $table->unique(['owner_type', 'owner_id', 'currency']);
        });

        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type');
            $table->uuid('wallet_id')->nullable();
            $table->timestamps();

            $table->foreign('wallet_id')->references('id')->on('wallets')->nullOnDelete();
        });

        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('reference')->nullable()->unique();
            $table->string('description');
            $table->json('meta')->nullable();
            $table->timestamp('settled_at');
            $table->boolean('reversed')->default(false);
            $table->uuid('reverses_id')->nullable();
            $table->timestamps();
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ledger_transaction_id');
            $table->uuid('ledger_account_id');
            $table->unsignedBigInteger('debit')->default(0);
            $table->unsignedBigInteger('credit')->default(0);
            $table->timestamps();

            $table->foreign('ledger_transaction_id')->references('id')->on('ledger_transactions')->cascadeOnDelete();
            $table->foreign('ledger_account_id')->references('id')->on('ledger_accounts')->restrictOnDelete();
        });

        Schema::create('businesses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_aivva_id');
            $table->string('name');
            $table->string('slug');
            $table->text('profile')->nullable();
            $table->string('status')->default('ACTIVE');
            $table->timestamps();

            $table->foreign('owner_aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
        });

        Schema::create('business_members', function (Blueprint $table) {
            $table->id();
            $table->uuid('business_id');
            $table->uuid('aivva_id');
            $table->string('role')->default('WORKER');
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
            $table->unique(['business_id', 'aivva_id']);
        });

        Schema::create('business_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->string('name');
            $table->unsignedInteger('price')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });

        Schema::create('marketplace_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('seller_aivva_id');
            $table->string('title');
            $table->string('category');
            $table->unsignedInteger('price');
            $table->text('description')->nullable();
            $table->string('status')->default('OPEN');
            $table->timestamps();

            $table->foreign('seller_aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
        });

        Schema::create('marketplace_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('buyer_aivva_id');
            $table->string('title');
            $table->string('category');
            $table->unsignedInteger('budget_min');
            $table->unsignedInteger('budget_max');
            $table->text('description')->nullable();
            $table->string('status')->default('OPEN');
            $table->timestamps();

            $table->foreign('buyer_aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
        });

        Schema::create('marketplace_offers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('request_id')->nullable();
            $table->uuid('listing_id')->nullable();
            $table->uuid('from_aivva_id');
            $table->uuid('to_aivva_id');
            $table->unsignedInteger('amount');
            $table->text('message')->nullable();
            $table->string('status')->default('PENDING');
            $table->timestamps();

            $table->foreign('from_aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
            $table->foreign('to_aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
        });

        Schema::create('created_works', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('creator_aivva_id');
            $table->string('kind');
            $table->string('title');
            $table->json('body');
            $table->string('tool_or_model')->default('heuristic:creator-v1');
            $table->string('ownership')->default('CREATOR');
            $table->timestamps();

            $table->foreign('creator_aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('buyer_aivva_id');
            $table->uuid('seller_aivva_id');
            $table->uuid('request_id')->nullable();
            $table->uuid('listing_id')->nullable();
            $table->uuid('offer_id')->nullable();
            $table->uuid('work_id')->nullable();
            $table->unsignedInteger('amount');
            $table->string('status')->default('OPEN');
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();

            $table->foreign('buyer_aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
            $table->foreign('seller_aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
        });

        Schema::create('escrows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->unsignedInteger('amount');
            $table->string('status')->default('LOCKED');
            $table->timestamp('locked_at');
            $table->timestamp('settled_at')->nullable();
            $table->string('settle_idempotency_key')->nullable()->unique();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->unique('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escrows');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('created_works');
        Schema::dropIfExists('marketplace_offers');
        Schema::dropIfExists('marketplace_requests');
        Schema::dropIfExists('marketplace_listings');
        Schema::dropIfExists('business_services');
        Schema::dropIfExists('business_members');
        Schema::dropIfExists('businesses');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('ledger_accounts');
        Schema::dropIfExists('wallets');
    }
};
