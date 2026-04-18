<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\ShopItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class ShopItemSeeder extends Seeder
{
    public function run(): void
    {
        $teacher = User::where('email', 'profesor@plataforma.com')->first();
        $advancedCourse = Course::where('slug', 'informatica-medica-para-flujos-clinicos-digitales')->first();

        $items = [
            [
                'name' => 'Cupón 10% Marketplace',
                'slug' => 'cupon-10-marketplace',
                'description' => 'Reduce el precio de tu próxima compra de curso en 10%.',
                'type' => 'discount_coupon',
                'cost_coins' => 180,
                'minimum_level_required' => 2,
                'metadata' => ['discount_percent' => 10, 'code_prefix' => 'SAVE10'],
            ],
            [
                'name' => 'Cupón 20% Premium',
                'slug' => 'cupon-20-premium',
                'description' => 'Descuento premium para cursos de ticket alto.',
                'type' => 'discount_coupon',
                'cost_coins' => 320,
                'minimum_level_required' => 4,
                'metadata' => ['discount_percent' => 20, 'code_prefix' => 'SAVE20'],
            ],
            [
                'name' => 'Marco Avatar Aurora',
                'slug' => 'marco-avatar-aurora',
                'description' => 'Cosmético de perfil con borde neón para el avatar.',
                'type' => 'avatar_frame',
                'cost_coins' => 140,
                'minimum_level_required' => 2,
                'metadata' => ['frame_style' => 'aurora-neon', 'preview_color' => '#20d5ec'],
            ],
            [
                'name' => 'Título Veterano',
                'slug' => 'titulo-veterano',
                'description' => 'Muestra el rango de Veterano en comentarios y perfil.',
                'type' => 'profile_title',
                'cost_coins' => 260,
                'minimum_level_required' => 5,
                'metadata' => ['title' => 'Veterano del Campus'],
            ],
            [
                'name' => 'Título Maestro',
                'slug' => 'titulo-maestro',
                'description' => 'Títulos especiales para perfiles con mucha constancia.',
                'type' => 'profile_title',
                'cost_coins' => 520,
                'minimum_level_required' => 10,
                'metadata' => ['title' => 'Maestro de la Plataforma'],
            ],
            [
                'name' => 'Bonus Pack Clínico',
                'slug' => 'bonus-pack-clinico',
                'description' => 'Contenido premium asociado a flujos avanzados de informática médica.',
                'type' => 'premium_content',
                'cost_coins' => 480,
                'minimum_level_required' => 6,
                'course_id' => $advancedCourse?->id,
                'metadata' => [
                    'unlock_kind' => 'bonus_pack',
                    'resource_label' => 'Casos avanzados y checklist premium',
                ],
            ],
        ];

        foreach ($items as $item) {
            ShopItem::create(array_merge($item, [
                'created_by' => $teacher?->id,
                'is_active' => true,
            ]));
        }
    }
}
