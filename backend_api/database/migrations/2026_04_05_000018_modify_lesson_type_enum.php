<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Skip this migration for SQLite (doesn't support MODIFY COLUMN with ENUM)
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Modify the enum column to include 'game' type
        DB::statement("ALTER TABLE lessons MODIFY COLUMN type ENUM('video', 'text', 'quiz', 'game') DEFAULT 'video'");
    }

    public function down(): void
    {
        // Skip this migration for SQLite (doesn't support MODIFY COLUMN with ENUM)
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Revert back to original enum values
        DB::statement("ALTER TABLE lessons MODIFY COLUMN type ENUM('video', 'text', 'quiz') DEFAULT 'video'");
    }
};
