<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_answers', function (Blueprint $table) {
            // Rename answer to user_answer (as used by QuizController)
            $table->renameColumn('answer', 'user_answer');
        });

        // Change column type from text to json in separate statement
        Schema::table('user_answers', function (Blueprint $table) {
            $table->json('user_answer')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('user_answers', function (Blueprint $table) {
            // Revert column type first
            $table->text('user_answer')->nullable()->change();

            // Revert rename
            $table->renameColumn('user_answer', 'answer');
        });
    }
};
