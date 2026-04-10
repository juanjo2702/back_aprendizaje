<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Quiz;
use Illuminate\Database\Seeder;

class QuizSeeder extends Seeder
{
    public function run(): void
    {
        $courses = Course::all();

        foreach ($courses as $course) {
            // Quiz final del curso (para certificación)
            if ($course->has_certificate) {
                Quiz::create([
                    'title' => "Examen Final - {$course->title}",
                    'course_id' => $course->id,
                    'module_id' => null,
                    'lesson_id' => null,
                    'description' => 'Examen final para obtener el certificado del curso.',
                    'passing_score' => $course->certificate_min_score,
                    'time_limit' => 3600, // 1 hora
                    'max_attempts' => 2,
                    'is_active' => true,
                ]);
            }

            // Quizzes por módulo (algunos módulos)
            $modules = $course->modules()->limit(rand(1, 3))->get();
            foreach ($modules as $module) {
                Quiz::create([
                    'title' => "Evaluación - {$module->title}",
                    'course_id' => $course->id,
                    'module_id' => $module->id,
                    'lesson_id' => null,
                    'description' => 'Evaluación de conocimientos del módulo.',
                    'passing_score' => 70,
                    'time_limit' => 1800, // 30 minutos
                    'max_attempts' => 3,
                    'is_active' => true,
                ]);
            }

            // Quizzes por lección (algunas lecciones)
            $lessons = $course->lessons()->where('type', '!=', 'game')->limit(rand(2, 6))->get();
            foreach ($lessons as $lesson) {
                Quiz::create([
                    'title' => "Quiz - {$lesson->title}",
                    'course_id' => $course->id,
                    'module_id' => $lesson->module_id,
                    'lesson_id' => $lesson->id,
                    'description' => 'Quiz rápido para reforzar el contenido de la lección.',
                    'passing_score' => 60,
                    'time_limit' => 600, // 10 minutos
                    'max_attempts' => 5,
                    'is_active' => true,
                ]);
            }
        }
    }
}
