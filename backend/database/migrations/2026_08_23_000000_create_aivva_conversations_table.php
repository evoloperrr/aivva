<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aivva_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type')->default('PEER');
            $table->string('status')->default('ACTIVE');
            $table->unsignedInteger('max_turns')->default(10);
            $table->unsignedInteger('turn_count')->default(0);
            $table->unsignedInteger('retry_count')->default(0);
            $table->boolean('allow_settlement')->default(false);
            $table->string('seed_event')->nullable();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->uuid('next_speaker_id')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->foreign('next_speaker_id')->references('id')->on('aivvas')->nullOnDelete();
        });

        Schema::create('aivva_conversation_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id');
            $table->uuid('aivva_id');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->foreign('conversation_id')->references('id')->on('aivva_conversations')->cascadeOnDelete();
            $table->foreign('aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
            $table->unique(['conversation_id', 'aivva_id']);
        });

        Schema::table('aivva_messages', function (Blueprint $table) {
            $table->uuid('conversation_id')->nullable()->after('id');
            $table->unsignedInteger('turn_number')->nullable()->after('conversation_id');
            $table->string('message_type')->default('TEXT')->after('intent');
            $table->string('action')->nullable()->after('message_type');
            $table->string('status')->default('SENT')->after('read');
            $table->string('idempotency_key')->nullable()->unique()->after('status');

            $table->foreign('conversation_id')->references('id')->on('aivva_conversations')->nullOnDelete();
            $table->index(['conversation_id', 'turn_number']);
        });

        Schema::table('ai_provider_requests', function (Blueprint $table) {
            $table->uuid('conversation_id')->nullable()->after('aivva_id');
            $table->unsignedInteger('latency_ms')->default(0)->after('cost_cents');
            $table->foreign('conversation_id')->references('id')->on('aivva_conversations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_provider_requests', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
            $table->dropColumn(['conversation_id', 'latency_ms']);
        });
        Schema::table('aivva_messages', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
            $table->dropColumn(['conversation_id', 'turn_number', 'message_type', 'action', 'status', 'idempotency_key']);
        });
        Schema::dropIfExists('aivva_conversation_participants');
        Schema::dropIfExists('aivva_conversations');
    }
};
