<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Rename question_text to question (as used by QuizController)
            $table->renameColumn('question_text', 'question');

            // Add explanation field (used by QuizController line 112)
            $table->text('explanation')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Revert rename
            $table->renameColumn('question', 'question_text');

            // Drop added column
            $table->dropColumn('explanation');
        });
    }
};
