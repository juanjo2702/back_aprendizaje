<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->string('pdf_path')->nullable()->after('download_url');
            $table->string('verification_url')->nullable()->after('pdf_path');
            $table->string('issued_via')->default('manual')->after('verification_url');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn(['pdf_path', 'verification_url', 'issued_via']);
        });
    }
};
