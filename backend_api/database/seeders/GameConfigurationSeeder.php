<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\GameConfiguration;
use App\Models\GameType;
use Illuminate\Database\Seeder;

class GameConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $gameTypes = GameType::all();
        $courses = Course::all();

        foreach ($courses as $course) {
            // Configuración de juego a nivel de curso
            $gameType = $gameTypes->random();
            GameConfiguration::firstOrCreate(
                [
                    'course_id' => $course->id,
                    'module_id' => null,
                    'lesson_id' => null,
                    'game_type_id' => $gameType->id,
                ],
                [
                    'title' => "Juego de {$gameType->name} - {$course->title}",
                    'config' => $gameType->default_config ?? [
                        'difficulty' => 'medium',
                        'time_limit' => 300,
                        'rewards' => ['points' => 100, 'badge' => null],
                    ],
                    'max_score' => 100,
                    'time_limit' => rand(300, 900),
                    'max_attempts' => 3,
                    'is_active' => true,
                ]
            );

            // Configuraciones a nivel de módulo (algunos módulos)
            $modules = $course->modules()->limit(rand(1, 3))->get();
            foreach ($modules as $module) {
                $gameType = $gameTypes->random();
                GameConfiguration::firstOrCreate(
                    [
                        'course_id' => $course->id,
                        'module_id' => $module->id,
                        'lesson_id' => null,
                        'game_type_id' => $gameType->id,
                    ],
                    [
                        'title' => "Juego de {$gameType->name} - {$module->title}",
                        'config' => $gameType->default_config ?? [
                            'difficulty' => 'easy',
                            'time_limit' => 180,
                            'rewards' => ['points' => 50],
                        ],
                        'max_score' => 100,
                        'time_limit' => rand(180, 600),
                        'max_attempts' => 5,
                        'is_active' => true,
                    ]
                );
            }

            // Configuraciones a nivel de lección (algunas lecciones)
            $lessons = $course->lessons()->limit(rand(2, 5))->get();
            foreach ($lessons as $lesson) {
                $gameType = $gameTypes->random();
                GameConfiguration::firstOrCreate(
                    [
                        'course_id' => $course->id,
                        'module_id' => $lesson->module_id,
                        'lesson_id' => $lesson->id,
                        'game_type_id' => $gameType->id,
                    ],
                    [
                        'title' => "Juego de {$gameType->name} - {$lesson->title}",
                        'config' => $gameType->default_config ?? [
                            'difficulty' => 'hard',
                            'time_limit' => 120,
                            'rewards' => ['points' => 200, 'badge' => 'speedster'],
                        ],
                        'max_score' => 100,
                        'time_limit' => rand(120, 300),
                        'max_attempts' => 2,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
