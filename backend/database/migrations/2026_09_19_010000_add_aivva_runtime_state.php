<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aivvas', function (Blueprint $table) {
            $table->string('control_mode')->default('HUMAN')->after('status');
            $table->unsignedBigInteger('state_version')->default(1)->after('control_mode');
        });
        Schema::table('aivva_profiles', function (Blueprint $table) {
            $table->json('appearance')->nullable()->after('portrait_seed');
            $table->string('animation_profile')->nullable()->after('appearance');
        });
        Schema::table('aivva_permissions', function (Blueprint $table) {
            $table->boolean('allow_movement')->default(true);
            $table->boolean('allow_social_interaction')->default(true);
            $table->boolean('allow_public_chat')->default(false);
            $table->boolean('allow_private_chat')->default(false);
            $table->boolean('allow_purchases')->default(false);
            $table->boolean('allow_wallet')->default(false);
            $table->boolean('allow_creation')->default(false);
            $table->boolean('allow_work')->default(false);
            $table->boolean('allow_invites')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('aivva_permissions', fn (Blueprint $table) => $table->dropColumn(['allow_movement', 'allow_social_interaction', 'allow_public_chat', 'allow_private_chat', 'allow_purchases', 'allow_wallet', 'allow_creation', 'allow_work', 'allow_invites']));
        Schema::table('aivva_profiles', fn (Blueprint $table) => $table->dropColumn(['appearance', 'animation_profile']));
        Schema::table('aivvas', fn (Blueprint $table) => $table->dropColumn(['control_mode', 'state_version']));
    }
};
