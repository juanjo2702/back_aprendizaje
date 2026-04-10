<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\GameSession;
use App\Models\User;
use App\Models\UserQuizAttempt;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BadgeService
{
    /**
     * Verificar y otorgar badges relacionados con juegos.
     */
    public function checkGameBadges(User $user, ?GameSession $session = null)
    {
        $badges = Badge::where('type', 'like', 'game_%')->get();

        foreach ($badges as $badge) {
            if ($this->userHasBadge($user, $badge)) {
                continue;
            }

            if ($this->meetsCriteria($user, $badge, $session)) {
                $this->awardBadge($user, $badge);
            }
        }
    }

    /**
     * Verificar y otorgar badges relacionados con quizzes.
     */
    public function checkQuizBadges(User $user, ?UserQuizAttempt $attempt = null)
    {
        $badges = Badge::where('type', 'like', 'quiz_%')->orWhere('type', 'speedster')->get();

        foreach ($badges as $badge) {
            if ($this->userHasBadge($user, $badge)) {
                continue;
            }

            if ($this->meetsCriteria($user, $badge, null, $attempt)) {
                $this->awardBadge($user, $badge);
            }
        }
    }

    /**
     * Verificar y otorgar badges generales (puntos, racha, etc.).
     */
    public function checkGeneralBadges(User $user)
    {
        $badges = Badge::whereIn('type', ['points', 'streak', 'course_completion'])->get();

        foreach ($badges as $badge) {
            if ($this->userHasBadge($user, $badge)) {
                continue;
            }

            if ($this->meetsCriteria($user, $badge)) {
                $this->awardBadge($user, $badge);
            }
        }
    }

    /**
     * Verificar si el usuario cumple con los criterios de un badge.
     */
    private function meetsCriteria(User $user, Badge $badge, ?GameSession $session = null, ?UserQuizAttempt $attempt = null): bool
    {
        $criteria = $badge->criteria;

        switch ($badge->type) {
            case 'game_master':
                $perfectGames = GameSession::where('user_id', $user->id)
                    ->where('status', 'completed')
                    ->whereRaw('score = game_configurations.max_score')
                    ->join('game_configurations', 'game_sessions.game_config_id', '=', 'game_configurations.id')
                    ->count();

                return $perfectGames >= ($criteria['perfect_games'] ?? 20);

            case 'points':
                return $user->total_points >= ($criteria['total_points'] ?? 1000);

            case 'streak':
                return $user->current_streak >= ($criteria['streak_days'] ?? 7);

            case 'speedster':
                if (! $attempt) {
                    return false;
                }

                return $attempt->time_spent <= ($criteria['quiz_time'] ?? 120); // segundos

            case 'course_completion':
                $completedCourses = DB::table('enrollments')
                    ->where('user_id', $user->id)
                    ->where('progress', '>=', 100)
                    ->count();

                return $completedCourses >= ($criteria['courses_completed'] ?? 1);

            case 'quiz_expert':
                $perfectQuizzes = UserQuizAttempt::where('user_id', $user->id)
                    ->where('status', 'completed')
                    ->where('percentage', 100)
                    ->count();

                return $perfectQuizzes >= ($criteria['perfect_quizzes'] ?? 10);

            default:
                return false;
        }
    }

    /**
     * Otorgar un badge al usuario.
     */
    private function awardBadge(User $user, Badge $badge): void
    {
        DB::table('user_badges')->insert([
            'user_id' => $user->id,
            'badge_id' => $badge->id,
            'earned_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // Otorgar puntos por badge
        $points = 50; // puntos base por badge
        $user->total_points += $points;
        $user->save();

        DB::table('points_log')->insert([
            'user_id' => $user->id,
            'points' => $points,
            'source' => 'badge',
            'source_id' => $badge->id,
            'description' => 'Puntos por obtener badge: '.$badge->name,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // Podríamos enviar una notificación aquí
        \Log::info("Usuario {$user->id} obtuvo el badge: {$badge->name}");
    }

    /**
     * Verificar si el usuario ya tiene un badge.
     */
    private function userHasBadge(User $user, Badge $badge): bool
    {
        return DB::table('user_badges')
            ->where('user_id', $user->id)
            ->where('badge_id', $badge->id)
            ->exists();
    }

    /**
     * Obtener badges del usuario con detalles.
     */
    public function getUserBadges(User $user)
    {
        return DB::table('user_badges')
            ->join('badges', 'user_badges.badge_id', '=', 'badges.id')
            ->where('user_badges.user_id', $user->id)
            ->select('badges.*', 'user_badges.earned_at')
            ->orderBy('user_badges.earned_at', 'desc')
            ->get();
    }

    /**
     * Obtener badges disponibles que el usuario aún no tiene.
     */
    public function getAvailableBadges(User $user)
    {
        $userBadgeIds = DB::table('user_badges')
            ->where('user_id', $user->id)
            ->pluck('badge_id');

        return Badge::whereNotIn('id', $userBadgeIds)
            ->orderBy('type')
            ->get();
    }
}
