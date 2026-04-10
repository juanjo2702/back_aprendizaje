<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $courses = Course::all();

        foreach ($courses as $course) {
            $moduleCount = rand(3, 8);

            for ($i = 1; $i <= $moduleCount; $i++) {
                Module::firstOrCreate(
                    ['course_id' => $course->id, 'sort_order' => $i],
                    [
                        'title' => $this->generateModuleTitle($course->title, $i),
                        'description' => fake()->paragraph(),
                    ]
                );
            }
        }
    }

    private function generateModuleTitle(string $courseTitle, int $index): string
    {
        $prefixes = [
            'Introducción a',
            'Fundamentos de',
            'Avanzado en',
            'Prácticas con',
            'Proyecto Final de',
            'Herramientas para',
            'Optimización de',
            'Despliegue de',
        ];

        $keywords = [
            'Conceptos Básicos',
            'Técnicas Principales',
            'Ejercicios Prácticos',
            'Casos de Estudio',
            'Buenas Prácticas',
            'Soluciones Avanzadas',
            'Integración Continua',
            'Monitoreo y Mantenimiento',
        ];

        $prefix = $prefixes[array_rand($prefixes)];
        $keyword = $keywords[array_rand($keywords)];

        return "$prefix ".explode(':', $courseTitle)[0]." - $keyword";
    }
}
