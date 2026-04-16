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

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE lessons MODIFY COLUMN type VARCHAR(40) NOT NULL DEFAULT 'video'");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE lessons ALTER COLUMN type TYPE VARCHAR(40)");
            DB::statement("ALTER TABLE lessons ALTER COLUMN type SET DEFAULT 'video'");
        }

        Schema::table('lessons', function (Blueprint $table) {
            if (! Schema::hasColumn('lessons', 'contentable_type')) {
                $table->nullableMorphs('contentable');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            if (Schema::hasColumn('lessons', 'contentable_type')) {
                $table->dropMorphs('contentable');
            }
        });
    }
};

