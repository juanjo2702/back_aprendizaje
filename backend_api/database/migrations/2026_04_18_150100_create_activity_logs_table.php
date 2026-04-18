<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interactive_config_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->unsignedTinyInteger('score');
            $table->unsignedTinyInteger('passing_score');
            $table->unsignedInteger('xp_awarded')->default(0);
            $table->unsignedInteger('coin_awarded')->default(0);
            $table->decimal('reward_multiplier', 5, 2)->default(1);
            $table->enum('status', ['passed', 'failed', 'locked'])->default('failed');
            $table->json('payload')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamps();

            $table->index(['interactive_config_id', 'user_id', 'attempt_number'], 'activity_logs_config_user_attempt_idx');
            $table->index(['course_id', 'lesson_id', 'status'], 'activity_logs_course_lesson_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
