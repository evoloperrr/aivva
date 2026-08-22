<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trust_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('aivva_id');
            $table->unsignedTinyInteger('economic')->default(50);
            $table->unsignedTinyInteger('social')->default(50);
            $table->json('skills')->nullable();
            $table->unsignedTinyInteger('overall')->default(50);
            $table->timestamps();

            $table->foreign('aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
            $table->unique('aivva_id');
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reviewer_aivva_id');
            $table->uuid('subject_aivva_id');
            $table->uuid('order_id')->nullable();
            $table->unsignedTinyInteger('rating');
            $table->text('body')->nullable();
            $table->timestamps();

            $table->foreign('reviewer_aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
            $table->foreign('subject_aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
        });

        Schema::create('verification_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('claim');
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->json('report')->nullable();
            $table->string('status')->default('OPEN');
            $table->timestamps();
        });

        Schema::create('verification_evidence', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('verification_case_id');
            $table->string('source');
            $table->text('content');
            $table->unsignedTinyInteger('source_confidence')->default(50);
            $table->boolean('is_contradiction')->default(false);
            $table->timestamps();

            $table->foreign('verification_case_id')->references('id')->on('verification_cases')->cascadeOnDelete();
        });

        Schema::create('disputes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->nullable();
            $table->uuid('opened_by_aivva_id');
            $table->text('reason');
            $table->json('report')->nullable();
            $table->string('status')->default('OPEN');
            $table->boolean('human_appeal_available')->default(true);
            $table->timestamps();
        });

        Schema::create('life_point_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('aivva_id');
            $table->integer('delta');
            $table->string('reason');
            $table->timestamps();

            $table->foreign('aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
        });

        Schema::create('ai_provider_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('aivva_id')->nullable();
            $table->string('provider');
            $table->string('model');
            $table->string('purpose');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cost_cents')->default(0);
            $table->string('status')->default('OK');
            $table->timestamps();
        });

        Schema::create('owner_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_notifications');
        Schema::dropIfExists('ai_provider_requests');
        Schema::dropIfExists('life_point_events');
        Schema::dropIfExists('disputes');
        Schema::dropIfExists('verification_evidence');
        Schema::dropIfExists('verification_cases');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('trust_scores');
    }
};
