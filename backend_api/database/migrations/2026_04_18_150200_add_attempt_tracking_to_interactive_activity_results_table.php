<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interactive_activity_results', function (Blueprint $table) {
            $table->unsignedInteger('attempts_used')->default(0)->after('max_score');
            $table->unsignedInteger('coin_awarded')->default(0)->after('xp_awarded');
            $table->boolean('is_locked')->default(false)->after('status');
            $table->boolean('requires_teacher_reset')->default(false)->after('is_locked');
            $table->timestamp('last_attempt_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('interactive_activity_results', function (Blueprint $table) {
            $table->dropColumn([
                'attempts_used',
                'coin_awarded',
                'is_locked',
                'requires_teacher_reset',
                'last_attempt_at',
            ]);
        });
    }
};
