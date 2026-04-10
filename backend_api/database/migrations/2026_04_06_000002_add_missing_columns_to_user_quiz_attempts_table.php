<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_quiz_attempts', function (Blueprint $table) {
            // Add hierarchical foreign keys for context
            $table->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained()->cascadeOnDelete();

            // Add status enum as used by QuizController
            $table->enum('status', ['in_progress', 'completed'])->default('in_progress');

            // Add timestamp for when attempt started
            $table->timestamp('started_at')->nullable();

            // Add percentage field for score percentage (matches controller)
            $table->decimal('percentage', 5, 2)->nullable()->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('user_quiz_attempts', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['course_id']);
            $table->dropForeign(['module_id']);

            // Drop columns
            $table->dropColumn(['course_id', 'module_id', 'status', 'started_at', 'percentage']);
        });
    }
};
