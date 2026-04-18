<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->resetScenarioTables();

        $this->call([
            GameTypeSeeder::class,
            BadgeSeeder::class,
            CertificateTemplateSeeder::class,
            PlatformUserSeeder::class,
            LmsCatalogSeeder::class,
            ShopItemSeeder::class,
            StudentSeeder::class,
        ]);
    }

    private function resetScenarioTables(): void
    {
        $tables = [
            'interactive_activity_results',
            'user_lesson_progress',
            'user_quiz_attempts',
            'game_sessions',
            'points_log',
            'comments',
            'purchases',
            'shop_items',
            'user_badges',
            'certificates',
            'payments',
            'enrollments',
            'questions',
            'quizzes',
            'game_configurations',
            'interactive_configs',
            'lesson_resources',
            'lesson_readings',
            'lesson_videos',
            'lessons',
            'modules',
            'courses',
            'categories',
            'users',
            'badges',
            'game_types',
            'certificate_templates',
        ];

        Schema::disableForeignKeyConstraints();

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();
    }
}
