<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Sistema base de gamificación y certificación
            GameTypeSeeder::class,
            BadgeSeeder::class,
            CertificateTemplateSeeder::class,

            // Contenido base
            UserSeeder::class,
            CategorySeeder::class,
            CourseSeeder::class,
            ModuleSeeder::class,

            // Gamificación y evaluación (dependen de contenido base)
            GameConfigurationSeeder::class,  // Configuraciones de curso y módulo
            QuizSeeder::class,               // Quizzes de curso y módulo
            
            // Lecciones (dependen de contenido base y gamificación)
            LessonSeeder::class,

            // Preguntas para quizzes (dependen de quizzes)
            QuestionSeeder::class,

            // Datos de uso (dependen de usuarios y contenido)
            EnrollmentSeeder::class,
            GameSessionSeeder::class,
            UserQuizAttemptSeeder::class,
            CertificateSeeder::class,
        ]);
    }
}
