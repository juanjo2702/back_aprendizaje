<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('certificate_requires_final_exam')->default(false)->after('has_certificate');
            $table->foreignId('certificate_final_lesson_id')->nullable()->after('certificate_requires_final_exam')->constrained('lessons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('certificate_final_lesson_id');
            $table->dropColumn('certificate_requires_final_exam');
        });
    }
};
