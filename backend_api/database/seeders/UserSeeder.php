<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Usuarios específicos para demostración
        $demoUsers = [
            [
                'name' => 'Administrador Plataforma',
                'email' => 'admin@plataforma.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'avatar' => 'https://i.pravatar.cc/300?img=1',
                'bio' => 'Administrador principal de la plataforma con acceso total al sistema.',
                'total_points' => 1500,
                'current_streak' => 14,
                'last_active_at' => now(),
            ],
            [
                'name' => 'Carlos Mendoza',
                'email' => 'profesor@plataforma.com',
                'password' => Hash::make('password'),
                'role' => 'instructor',
                'avatar' => 'https://i.pravatar.cc/300?img=5',
                'bio' => 'Desarrollador Full-Stack con 10 años de experiencia en PHP, Laravel y Vue.js. Apasionado por enseñar.',
                'total_points' => 850,
                'current_streak' => 7,
                'last_active_at' => now()->subDays(1),
            ],
            [
                'name' => 'Ana Gutiérrez',
                'email' => 'anag@plataforma.com',
                'password' => Hash::make('password'),
                'role' => 'instructor',
                'avatar' => 'https://i.pravatar.cc/300?img=8',
                'bio' => 'Diseñadora UX/UI especializada en Design Systems y Figma. Instructora certificada.',
                'total_points' => 720,
                'current_streak' => 3,
                'last_active_at' => now()->subHours(5),
            ],
            [
                'name' => 'Juan Pérez',
                'email' => 'estudiante@plataforma.com',
                'password' => Hash::make('password'),
                'role' => 'student',
                'avatar' => 'https://i.pravatar.cc/300?img=12',
                'bio' => 'Estudiante de ingeniería de software apasionado por el aprendizaje continuo.',
                'total_points' => 320,
                'current_streak' => 5,
                'last_active_at' => now()->subHours(2),
            ],
        ];

        foreach ($demoUsers as $user) {
            User::firstOrCreate(['email' => $user['email']], $user);
        }

        // Generar usuarios adicionales con Faker
        User::factory()->count(10)->create([
            'role' => 'student',
            'total_points' => rand(0, 500),
            'current_streak' => rand(0, 10),
            'last_active_at' => now()->subDays(rand(0, 30)),
        ]);

        User::factory()->count(3)->create([
            'role' => 'instructor',
            'total_points' => rand(500, 1200),
            'current_streak' => rand(0, 15),
            'last_active_at' => now()->subDays(rand(0, 7)),
        ]);
    }
}
