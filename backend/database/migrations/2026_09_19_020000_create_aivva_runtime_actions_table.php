<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aivva_runtime_locations', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('label');
            $table->decimal('x', 10, 3);
            $table->decimal('y', 10, 3);
            $table->decimal('z', 10, 3)->default(0);
            $table->foreignId('logical_location_id')->nullable()->unique()->constrained('locations')->nullOnDelete();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('aivva_runtime_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('aivva_id');
            $table->uuid('source_action_id')->nullable()->unique();
            $table->string('type');
            $table->json('payload');
            $table->string('status')->default('REQUESTED');
            $table->uuid('correlation_id');
            $table->unsignedBigInteger('version')->default(1);
            $table->string('initiated_by')->default('AI');
            $table->string('execution_id')->nullable();
            $table->string('client_instance_id')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('lease_expires_at')->nullable()->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('failure_code')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();
            $table->foreign('aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
            $table->foreign('source_action_id')->references('id')->on('aivva_actions')->nullOnDelete();
            $table->index(['aivva_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aivva_runtime_actions');
        Schema::dropIfExists('aivva_runtime_locations');
    }
};
