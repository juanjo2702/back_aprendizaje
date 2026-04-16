<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        Schema::create('interactive_configs', function (Blueprint $table) use ($driver) {
            $table->id();
            $table->foreignId('lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('authoring_mode', ['form', 'custom'])->default('form');
            $table->string('activity_type')->default('trivia');

            if ($driver === 'pgsql') {
                $table->jsonb('config_payload');
                $table->jsonb('assets_manifest')->nullable();
            } else {
                $table->json('config_payload');
                $table->json('assets_manifest')->nullable();
            }

            $table->string('source_package_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique('lesson_id');
            $table->index(['course_id', 'module_id', 'activity_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interactive_configs');
    }
};

