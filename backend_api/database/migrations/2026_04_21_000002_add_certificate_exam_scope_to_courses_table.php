<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // 'lesson' = usa una lección específica como examen final
            // 'course' = promedia todas las actividades interactivas del curso
            $table->string('certificate_exam_scope', 10)
                ->default('lesson')
                ->after('certificate_final_lesson_id')
                ->comment('lesson | course');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('certificate_exam_scope');
        });
    }
};
