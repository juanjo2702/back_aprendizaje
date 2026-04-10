<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->foreignId('game_config_id')->nullable()->after('is_free')->constrained('game_configurations')->nullOnDelete();
            $table->foreignId('quiz_id')->nullable()->after('game_config_id')->constrained('quizzes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropForeign(['game_config_id']);
            $table->dropForeign(['quiz_id']);
            $table->dropColumn(['game_config_id', 'quiz_id']);
        });
    }
};
