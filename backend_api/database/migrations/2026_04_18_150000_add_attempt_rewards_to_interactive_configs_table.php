<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interactive_configs', function (Blueprint $table) {
            $table->unsignedInteger('max_attempts')->default(3)->after('activity_type');
            $table->unsignedTinyInteger('passing_score')->default(70)->after('max_attempts');
            $table->unsignedInteger('xp_reward')->default(100)->after('passing_score');
            $table->unsignedInteger('coin_reward')->default(25)->after('xp_reward');
        });
    }

    public function down(): void
    {
        Schema::table('interactive_configs', function (Blueprint $table) {
            $table->dropColumn(['max_attempts', 'passing_score', 'xp_reward', 'coin_reward']);
        });
    }
};
