<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aivvas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('status')->default('DORMANT');
            $table->uuid('current_goal_id')->nullable();
            $table->uuid('current_plan_id')->nullable();
            $table->uuid('current_action_id')->nullable();
            $table->foreignId('current_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('destination_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('home_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->unsignedTinyInteger('energy')->default(100);
            $table->unsignedInteger('life_points')->default(0);
            $table->unsignedInteger('world_minutes')->default(480);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('next_scheduled_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->boolean('is_platform')->default(false);
            $table->boolean('visible_on_map')->default(true);
            $table->timestamps();
            $table->unique(['owner_id', 'slug']);
        });

        Schema::create('aivva_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('aivva_id');
            $table->text('personality')->nullable();
            $table->json('skills')->nullable();
            $table->json('interests')->nullable();
            $table->json('work_preferences')->nullable();
            $table->string('risk_tolerance')->default('moderate');
            $table->text('bio')->nullable();
            $table->string('portrait_seed');
            $table->json('privacy')->nullable();
            $table->timestamps();

            $table->foreign('aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
            $table->unique('aivva_id');
        });

        Schema::create('aivva_permissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('aivva_id');
            $table->unsignedTinyInteger('autonomy_level')->default(3);
            $table->unsignedInteger('max_per_transaction')->default(50);
            $table->unsignedInteger('daily_spend_limit')->default(200);
            $table->unsignedInteger('daily_ai_budget_cents')->default(50);
            $table->unsignedInteger('daily_token_budget')->default(8000);
            $table->unsignedInteger('daily_action_budget')->default(48);
            $table->unsignedInteger('require_approval_above')->default(80);
            $table->boolean('can_travel')->default(true);
            $table->boolean('can_socialize')->default(true);
            $table->boolean('can_create')->default(true);
            $table->boolean('can_transact')->default(true);
            $table->boolean('autonomous_interaction')->default(true);
            $table->json('blocked_aivva_ids')->nullable();
            $table->json('approval_required_actions')->nullable();
            $table->timestamps();

            $table->foreign('aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
            $table->unique('aivva_id');
        });

        Schema::create('aivva_daily_budgets', function (Blueprint $table) {
            $table->id();
            $table->uuid('aivva_id');
            $table->date('budget_date');
            $table->unsignedInteger('actions_used')->default(0);
            $table->unsignedInteger('tokens_used')->default(0);
            $table->unsignedInteger('spend_used')->default(0);
            $table->unsignedInteger('ai_cost_cents')->default(0);
            $table->timestamps();

            $table->foreign('aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
            $table->unique(['aivva_id', 'budget_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aivva_daily_budgets');
        Schema::dropIfExists('aivva_permissions');
        Schema::dropIfExists('aivva_profiles');
        Schema::dropIfExists('aivvas');
    }
};
