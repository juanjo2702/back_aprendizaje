<?php

namespace Database\Seeders;

use App\Models\GameType;
use Illuminate\Database\Seeder;

class GameTypeSeeder extends Seeder
{
    public function run(): void
    {
        $gameTypes = [
            [
                'name' => 'Quiz',
                'slug' => 'quiz',
                'description' => 'Preguntas y respuestas para evaluar conocimiento',
                'default_config' => [
                    'question_count' => 10,
                    'time_per_question' => 30,
                    'shuffle_questions' => true,
                ],
            ],
            [
                'name' => 'Memory Game',
                'slug' => 'memory-game',
                'description' => 'Juego de memoria para emparejar conceptos',
                'default_config' => [
                    'card_count' => 12,
                    'theme' => 'tech',
                    'difficulty' => 'medium',
                ],
            ],
            [
                'name' => 'Puzzle',
                'slug' => 'puzzle',
                'description' => 'Rompecabezas para organizar conceptos en orden lógico',
                'default_config' => [
                    'piece_count' => 8,
                    'hint_enabled' => true,
                ],
            ],
            [
                'name' => 'Drag & Drop',
                'slug' => 'drag-drop',
                'description' => 'Arrastrar elementos a la categoría correcta',
                'default_config' => [
                    'item_count' => 6,
                    'categories' => 3,
                ],
            ],
            [
                'name' => 'Trivia',
                'slug' => 'trivia',
                'description' => 'Preguntas de cultura general sobre el tema',
                'default_config' => [
                    'rounds' => 3,
                    'questions_per_round' => 5,
                ],
            ],
        ];

        foreach ($gameTypes as $type) {
            GameType::firstOrCreate(['slug' => $type['slug']], $type);
        }
    }
}
