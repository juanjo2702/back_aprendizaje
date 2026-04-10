<?php

namespace Database\Seeders;

use App\Models\GameConfiguration;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Database\Seeder;

class GameSessionSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('role', 'student')->get();
        $gameConfigs = GameConfiguration::where('is_active', true)->get();

        foreach ($users as $user) {
            $sessionCount = rand(5, 20);
            $configs = $gameConfigs->random(min($sessionCount, $gameConfigs->count()));

            foreach ($configs as $config) {
                $attempts = rand(1, $config->max_attempts);

                for ($attempt = 1; $attempt <= $attempts; $attempt++) {
                    $score = rand(0, $config->max_score);
                    $completed = $score >= ($config->max_score * 0.7);

                    GameSession::create([
                        'user_id' => $user->id,
                        'game_config_id' => $config->id,
                        'score' => $score,
                        'time_spent' => rand(30, $config->time_limit ?? 600),
                        'attempt' => $attempt,
                        'game_data' => [
                            'level' => $completed ? 'completed' : 'failed',
                            'stars' => floor($score / ($config->max_score / 5)),
                            'items_collected' => rand(0, 10),
                        ],
                        'completed_at' => $completed ? now()->subDays(rand(0, 30)) : null,
                    ]);

                    // Actualizar puntos del usuario
                    if ($completed) {
                        $user->total_points += $score;
                        $user->save();
                    }
                }
            }
        }
    }
}
