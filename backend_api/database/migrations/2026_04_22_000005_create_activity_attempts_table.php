<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interactive_config_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->decimal('score', 8, 2)->default(0);
            $table->decimal('max_score', 8, 2)->default(100);
            $table->decimal('score_percentage', 5, 2)->default(0);
            $table->unsignedTinyInteger('passing_score')->default(70);
            $table->integer('xp_awarded')->default(0);
            $table->integer('xp_penalty')->default(0);
            $table->unsignedInteger('coin_awarded')->default(0);
            $table->boolean('passed')->default(false);
            $table->boolean('locked')->default(false);
            $table->json('payload')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamps();

            $table->index(['interactive_config_id', 'user_id', 'attempt_number'], 'activity_attempts_config_user_attempt_idx');
            $table->index(['course_id', 'lesson_id', 'passed'], 'activity_attempts_course_lesson_passed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_attempts');
    }
};
