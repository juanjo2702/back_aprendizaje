<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('game_type_id')->constrained();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained();
            $table->foreignId('lesson_id')->nullable()->constrained();
            $table->json('config');
            $table->integer('max_score')->default(100);
            $table->integer('time_limit')->nullable();
            $table->integer('max_attempts')->default(3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_configurations');
    }
};
