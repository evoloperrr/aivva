<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aivva_goals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('aivva_id');
            $table->text('raw_direction');
            $table->string('goal_type')->nullable();
            $table->json('structured')->nullable();
            $table->string('status')->default('DRAFT');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->boolean('rejected')->default(false);
            $table->string('rejection_reason')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->foreign('aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
        });

        Schema::create('aivva_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('aivva_id');
            $table->uuid('goal_id');
            $table->json('steps');
            $table->unsignedInteger('current_step')->default(0);
            $table->string('status')->default('ACTIVE');
            $table->timestamps();

            $table->foreign('aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
            $table->foreign('goal_id')->references('id')->on('aivva_goals')->cascadeOnDelete();
        });

        Schema::create('aivva_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('aivva_id');
            $table->uuid('goal_id')->nullable();
            $table->uuid('plan_id')->nullable();
            $table->string('type');
            $table->json('payload')->nullable();
            $table->string('status')->default('PENDING');
            $table->string('initiated_by')->default('AI');
            $table->string('reason_category')->nullable();
            $table->unsignedTinyInteger('permission_level_used')->nullable();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->unsignedInteger('credit_cost')->default(0);
            $table->unsignedInteger('token_cost')->default(0);
            $table->json('result')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
        });

        Schema::create('aivva_activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('aivva_id');
            $table->uuid('action_id')->nullable();
            $table->string('kind');
            $table->string('headline');
            $table->text('body')->nullable();
            $table->unsignedInteger('world_minutes')->default(0);
            $table->json('meta')->nullable();
            $table->boolean('notify_owner')->default(false);
            $table->timestamps();

            $table->foreign('aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
            $table->index(['aivva_id', 'created_at']);
        });

        Schema::create('aivva_memories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('aivva_id');
            $table->string('category');
            $table->text('content');
            $table->unsignedTinyInteger('importance')->default(3);
            $table->json('related')->nullable();
            $table->boolean('is_private')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
            $table->index(['aivva_id', 'category']);
        });

        Schema::create('aivva_relationships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('aivva_id');
            $table->uuid('other_aivva_id');
            $table->string('type')->default('acquaintance');
            $table->unsignedTinyInteger('strength')->default(10);
            $table->unsignedTinyInteger('trust')->default(20);
            $table->unsignedInteger('interaction_count')->default(0);
            $table->timestamp('last_interaction_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
            $table->foreign('other_aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
            $table->unique(['aivva_id', 'other_aivva_id']);
        });

        Schema::create('aivva_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('from_aivva_id');
            $table->uuid('to_aivva_id');
            $table->string('intent');
            $table->json('payload');
            $table->text('natural_language')->nullable();
            $table->string('layer')->default('EXTERNAL_CONTENT');
            $table->boolean('read')->default(false);
            $table->timestamps();

            $table->foreign('from_aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
            $table->foreign('to_aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
        });

        Schema::create('travel_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('aivva_id');
            $table->foreignId('from_location_id')->constrained('locations');
            $table->foreignId('to_location_id')->constrained('locations');
            $table->decimal('distance', 8, 2);
            $table->unsignedInteger('world_minutes_duration');
            $table->timestamp('started_at');
            $table->timestamp('arrives_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('status')->default('TRAVELING');
            $table->timestamps();

            $table->foreign('aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
        });

        Schema::table('aivvas', function (Blueprint $table) {
            $table->foreign('current_goal_id')->references('id')->on('aivva_goals')->nullOnDelete();
            $table->foreign('current_plan_id')->references('id')->on('aivva_plans')->nullOnDelete();
            $table->foreign('current_action_id')->references('id')->on('aivva_actions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('aivvas', function (Blueprint $table) {
            $table->dropForeign(['current_goal_id']);
            $table->dropForeign(['current_plan_id']);
            $table->dropForeign(['current_action_id']);
        });
        Schema::dropIfExists('travel_events');
        Schema::dropIfExists('aivva_messages');
        Schema::dropIfExists('aivva_relationships');
        Schema::dropIfExists('aivva_memories');
        Schema::dropIfExists('aivva_activity_logs');
        Schema::dropIfExists('aivva_actions');
        Schema::dropIfExists('aivva_plans');
        Schema::dropIfExists('aivva_goals');
    }
};
