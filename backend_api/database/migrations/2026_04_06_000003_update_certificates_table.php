<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            // Rename certificate_number to certificate_code (maintaining unique constraint)
            $table->renameColumn('certificate_number', 'certificate_code');

            // Rename issue_date to issued_at and change from date to timestamp
            $table->renameColumn('issue_date', 'issued_at');

            // Add new columns required by CertificateController
            $table->string('student_name')->nullable();
            $table->string('course_name')->nullable();
            $table->json('metadata')->nullable();
        });

        // Need separate statement to change column type (can't chain rename and change)
        Schema::table('certificates', function (Blueprint $table) {
            $table->timestamp('issued_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            // Revert column type change first
            $table->date('issued_at')->nullable()->change();

            // Revert renames
            $table->renameColumn('certificate_code', 'certificate_number');
            $table->renameColumn('issued_at', 'issue_date');

            // Drop added columns
            $table->dropColumn(['student_name', 'course_name', 'metadata']);
        });
    }
};
