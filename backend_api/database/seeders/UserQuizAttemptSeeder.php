<?php

namespace Database\Seeders;

use App\Models\Quiz;
use App\Models\User;
use App\Models\UserQuizAttempt;
use Illuminate\Database\Seeder;

class UserQuizAttemptSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('role', 'student')->get();
        $quizzes = Quiz::where('is_active', true)->get();

        foreach ($users as $user) {
            $attemptCount = rand(3, 10);
            $userQuizzes = $quizzes->random(min($attemptCount, $quizzes->count()));

            foreach ($userQuizzes as $quiz) {
                $maxAttempts = rand(1, $quiz->max_attempts);

                for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                    $score = rand(0, 100);
                    $passed = $score >= $quiz->passing_score;

                    UserQuizAttempt::create([
                        'user_id' => $user->id,
                        'quiz_id' => $quiz->id,
                        'score' => $score,
                        'total_questions' => $quiz->questions()->count(),
                        'correct_answers' => floor($quiz->questions()->count() * ($score / 100)),
                        'time_spent' => rand(60, $quiz->time_limit ?? 1800),
                        'completed_at' => $passed ? now()->subDays(rand(0, 30)) : null,
                    ]);
                }
            }
        }
    }
}
