<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PlatformUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Administrador Plataforma',
                'email' => 'admin@plataforma.com',
                'role' => 'admin',
                'avatar' => 'https://i.pravatar.cc/300?img=1',
                'bio' => 'Administra la operación integral del LMS gamificado.',
                'total_points' => 1850,
                'current_streak' => 18,
                'last_active_at' => now()->subHours(3),
            ],
            [
                'name' => 'Carlos Mendoza',
                'email' => 'profesor@plataforma.com',
                'role' => 'instructor',
                'avatar' => 'https://i.pravatar.cc/300?img=5',
                'bio' => 'Arquitecto Full-stack especializado en Laravel, Quasar y APIs empresariales.',
                'total_points' => 1420,
                'current_streak' => 9,
                'last_active_at' => now()->subHours(5),
            ],
            [
                'name' => 'Ana Gutiérrez',
                'email' => 'anag@plataforma.com',
                'role' => 'instructor',
                'avatar' => 'https://i.pravatar.cc/300?img=8',
                'bio' => 'Especialista en experiencia de usuario y visualización de información clínica.',
                'total_points' => 1195,
                'current_streak' => 6,
                'last_active_at' => now()->subDay(),
            ],
            [
                'name' => 'Dra. Sara Molina',
                'email' => 'sara.molina@plataforma.com',
                'role' => 'instructor',
                'avatar' => 'https://i.pravatar.cc/300?img=32',
                'bio' => 'Consultora en informática médica y transformación digital hospitalaria.',
                'total_points' => 1310,
                'current_streak' => 11,
                'last_active_at' => now()->subHours(10),
            ],
            [
                'name' => 'Diego Murillo',
                'email' => 'diego.murillo@plataforma.com',
                'role' => 'instructor',
                'avatar' => 'https://i.pravatar.cc/300?img=15',
                'bio' => 'Ingeniero de plataformas, observabilidad y automatización de operaciones.',
                'total_points' => 1275,
                'current_streak' => 8,
                'last_active_at' => now()->subHours(8),
            ],
        ];

        foreach ($users as $user) {
            User::create(array_merge($user, [
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]));
        }
    }
}
