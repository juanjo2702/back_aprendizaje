<?php

namespace Database\Seeders;

use App\Models\GameConfiguration;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Quiz;
use Illuminate\Database\Seeder;

class LessonSeeder extends Seeder
{
    public function run(): void
    {
        $modules = Module::with('course')->get();

        foreach ($modules as $module) {
            $lessonCount = rand(4, 10);
            $lessons = [];

            for ($i = 1; $i <= $lessonCount; $i++) {
                $type = $this->determineLessonType($i, $lessonCount);

                $lessonData = [
                    'module_id' => $module->id,
                    'title' => $this->generateLessonTitle($type, $i, $module->course->title),
                    'type' => $type,
                    'content_url' => $type === 'video' ? 'https://player.vimeo.com/video/'.fake()->numberBetween(10000000, 99999999) : null,
                    'content_text' => $type === 'text' ? fake()->paragraphs(3, true) : null,
                    'duration' => rand(300, 3600),
                    'sort_order' => $i,
                    'is_free' => $i <= 2,
                    'game_config_id' => null,
                    'quiz_id' => null,
                ];

                // Asignar game o quiz si corresponde
                if ($type === 'game') {
                    $gameConfig = GameConfiguration::where('module_id', $module->id)
                        ->orWhere('course_id', $module->course_id)
                        ->inRandomOrder()
                        ->first();
                    if ($gameConfig) {
                        $lessonData['game_config_id'] = $gameConfig->id;
                    }
                } elseif ($type === 'quiz') {
                    $quiz = Quiz::where('module_id', $module->id)
                        ->orWhere('course_id', $module->course_id)
                        ->inRandomOrder()
                        ->first();
                    if ($quiz) {
                        $lessonData['quiz_id'] = $quiz->id;
                    }
                }

                $lessons[] = $lessonData;
            }

            // Insertar lecciones una por una para evitar problemas de columnas
            foreach ($lessons as $lesson) {
                Lesson::firstOrCreate(
                    ['module_id' => $lesson['module_id'], 'sort_order' => $lesson['sort_order']],
                    $lesson
                );
            }
        }
    }

    private function determineLessonType(int $position, int $total): string
    {
        $types = ['video', 'text', 'video', 'text', 'quiz', 'game'];

        if ($position === 1) {
            return 'video';
        }
        if ($position === $total) {
            return 'quiz';
        }

        $weights = [
            'video' => 40,
            'text' => 30,
            'quiz' => 20,
            'game' => 10,
        ];

        $random = rand(1, 100);
        $cumulative = 0;

        foreach ($weights as $type => $weight) {
            $cumulative += $weight;
            if ($random <= $cumulative) {
                return $type;
            }
        }

        return 'video';
    }

    private function generateLessonTitle(string $type, int $index, string $courseTitle): string
    {
        $prefixes = [
            'video' => ['Introducción a', 'Conceptos de', 'Práctica con', 'Demostración de'],
            'text' => ['Lectura sobre', 'Resumen de', 'Artículo sobre', 'Teoría de'],
            'quiz' => ['Evaluación de', 'Quiz sobre', 'Test de conocimientos de'],
            'game' => ['Juego interactivo de', 'Desafío de', 'Actividad gamificada de'],
        ];

        $topics = [
            'Fundamentos',
            'Técnicas Avanzadas',
            'Ejemplos Prácticos',
            'Casos de Estudio',
            'Buenas Prácticas',
            'Herramientas Esenciales',
        ];

        $prefix = $prefixes[$type][array_rand($prefixes[$type])];
        $topic = $topics[array_rand($topics)];

        return "$prefix $topic - Lección $index";
    }
}
