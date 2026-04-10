<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('has_certificate')->default(false)->after('what_you_learn');
            $table->integer('certificate_min_score')->default(70)->after('has_certificate');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['has_certificate', 'certificate_min_score']);
        });
    }
};
