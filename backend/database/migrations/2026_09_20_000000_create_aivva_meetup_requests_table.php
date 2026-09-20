<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aivva_meetup_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('from_aivva_id');
            $table->uuid('to_aivva_id');
            $table->string('proposed_location_id', 191);
            $table->string('status', 16)->default('PENDING');
            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->foreign('from_aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
            $table->foreign('to_aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
            $table->index(['to_aivva_id', 'status', 'expires_at']);
            $table->index(['from_aivva_id', 'status']);
            $table->unique(['from_aivva_id', 'to_aivva_id', 'proposed_location_id', 'status'], 'aivva_meetup_active_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aivva_meetup_requests');
    }
};
