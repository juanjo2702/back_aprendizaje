<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            // Add hierarchical foreign keys (nullable as games might be standalone)
            $table->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->cascadeOnDelete();

            // Add status enum as used by GameSessionController
            $table->enum('status', ['started', 'completed'])->default('started');

            // Add timestamps for tracking
            $table->timestamp('started_at')->nullable();

            // Add details field (controller uses this, game_data might be for game-specific data)
            $table->json('details')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['course_id']);
            $table->dropForeign(['module_id']);
            $table->dropForeign(['lesson_id']);

            // Drop columns
            $table->dropColumn(['course_id', 'module_id', 'lesson_id', 'status', 'started_at', 'details']);
        });
    }
};
