<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('provider')->nullable();
            $table->string('video_url');
            $table->string('embed_url')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('lesson_id');
        });

        Schema::create('lesson_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->longText('body_markdown')->nullable();
            $table->longText('body_html')->nullable();
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('lesson_id');
        });

        Schema::create('lesson_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_url');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->boolean('is_downloadable')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('lesson_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_resources');
        Schema::dropIfExists('lesson_readings');
        Schema::dropIfExists('lesson_videos');
    }
};

