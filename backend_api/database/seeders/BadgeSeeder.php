<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            [
                'name' => 'Primer Curso Completado',
                'slug' => 'primer-curso-completado',
                'description' => 'Completa tu primer curso en la plataforma',
                'icon' => 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png',
                'type' => 'course_completion',
                'criteria' => ['courses_completed' => 1],
            ],
            [
                'name' => 'Racha de 7 Días',
                'slug' => 'racha-7-dias',
                'description' => 'Accede a la plataforma 7 días consecutivos',
                'icon' => 'https://cdn-icons-png.flaticon.com/512/1828/1828884.png',
                'type' => 'streak',
                'criteria' => ['streak_days' => 7],
            ],
            [
                'name' => 'Maestro de Juegos',
                'slug' => 'maestro-de-juegos',
                'description' => 'Completa 20 juegos con puntaje perfecto',
                'icon' => 'https://cdn-icons-png.flaticon.com/512/201/201623.png',
                'type' => 'game_master',
                'criteria' => ['perfect_games' => 20],
            ],
            [
                'name' => 'Puntos Legendarios',
                'slug' => 'puntos-legendarios',
                'description' => 'Acumula más de 1000 puntos en la plataforma',
                'icon' => 'https://cdn-icons-png.flaticon.com/512/2171/2171428.png',
                'type' => 'points',
                'criteria' => ['total_points' => 1000],
            ],
            [
                'name' => 'Velocista',
                'slug' => 'velocista',
                'description' => 'Completa un quiz en menos de 2 minutos',
                'icon' => 'https://cdn-icons-png.flaticon.com/512/785/785116.png',
                'type' => 'speedster',
                'criteria' => ['quiz_time' => 120],
            ],
        ];

        foreach ($badges as $badge) {
            Badge::firstOrCreate(['slug' => $badge['slug']], $badge);
        }
    }
}
