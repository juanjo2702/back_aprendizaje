<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->timestamp('submitted_for_review_at')->nullable()->after('status');
            $table->timestamp('published_at')->nullable()->after('submitted_for_review_at');
            $table->foreignId('approved_by')->nullable()->after('published_at')->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'submitted_for_review_at',
                'published_at',
                'review_notes',
            ]);
        });
    }
};
