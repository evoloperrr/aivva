<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worlds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('map_width')->default(1000);
            $table->unsignedInteger('map_height')->default(640);
            $table->timestamps();
        });

        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();
            $table->unique(['world_id', 'slug']);
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['region_id', 'slug']);
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('theme')->nullable();
            $table->string('color')->nullable();
            $table->json('polygon')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['city_id', 'slug']);
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('type');
            $table->decimal('coord_x', 8, 2);
            $table->decimal('coord_y', 8, 2);
            $table->unsignedInteger('capacity')->default(50);
            $table->json('services')->nullable();
            $table->boolean('is_home_template')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['district_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('regions');
        Schema::dropIfExists('worlds');
    }
};
