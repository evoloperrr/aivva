<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_chats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('aivva_id');
            $table->string('role'); // owner | aivva | system
            $table->text('body');
            $table->string('intent')->default('chat');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('aivva_id')->references('id')->on('aivvas')->cascadeOnDelete();
            $table->index(['aivva_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_chats');
    }
};
