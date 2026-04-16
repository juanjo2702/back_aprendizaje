<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interactive_activity_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interactive_config_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('source_type', ['game_session', 'quiz_attempt', 'interactive_renderer']);
            $table->unsignedBigInteger('source_id');
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('max_score', 8, 2)->nullable();
            $table->unsignedInteger('xp_awarded')->default(0);
            $table->json('badges_awarded')->nullable();
            $table->enum('status', ['started', 'completed', 'failed'])->default('completed');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'source_type', 'source_id'], 'iar_user_source_unique');
            $table->index(['user_id', 'course_id', 'lesson_id'], 'iar_user_course_lesson_idx');
            $table->index(['user_id', 'course_id', 'status'], 'iar_user_course_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interactive_activity_results');
    }
};
