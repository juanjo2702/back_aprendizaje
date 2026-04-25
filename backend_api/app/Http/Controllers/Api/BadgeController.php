<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\User;
use App\Services\BadgeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BadgeController extends Controller
{
    protected $badgeService;

    public function __construct()
    {
        $this->badgeService = new BadgeService;
    }

    /**
     * Listar todos los badges disponibles en la plataforma.
     */
    public function index(Request $request)
    {
        $query = Badge::query();

        // Filtrar por tipo
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Buscar por nombre
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('description', 'like', "%{$request->search}%");
            });
        }

        return $query->orderBy('type')->orderBy('name')->paginate($request->get('per_page', 20));
    }

    /**
     * Obtener badges del usuario autenticado.
     */
    public function myBadges(Request $request)
    {
        $user = Auth::user();
        $this->badgeService->checkGeneralBadges($user);
        $user = $user->fresh();

        $badges = $this->badgeService->getUserBadges($user);

        // Agrupar por tipo para la respuesta
        $grouped = $badges->groupBy('type')->map(function ($items) {
            return [
                'count' => $items->count(),
                'badges' => $items,
            ];
        });

        return response()->json([
            'total' => $badges->count(),
            'by_type' => $grouped,
            'badges' => $badges,
        ]);
    }

    /**
     * Obtener badges disponibles que el usuario aún no ha obtenido.
     */
    public function availableBadges(Request $request)
    {
        $user = Auth::user();
        $this->badgeService->checkGeneralBadges($user);
        $user = $user->fresh();

        $badges = $this->badgeService->getAvailableBadges($user);

        // Para cada badge, calcular progreso hacia su obtención
        $badgesWithProgress = $badges->map(function ($badge) use ($user) {
            $badge->progress = $this->calculateBadgeProgress($user, $badge);

            return $badge;
        });

        return response()->json([
            'total_available' => $badgesWithProgress->count(),
            'badges' => $badgesWithProgress,
        ]);
    }

    /**
     * Mostrar un badge específico con información detallada.
     */
    public function show(Badge $badge)
    {
        $user = Auth::user();
        $this->badgeService->checkGeneralBadges($user);
        $user = $user->fresh();

        // Verificar si el usuario tiene este badge
        $hasBadge = $this->badgeService->userHasBadge($user, $badge);

        // Calcular progreso si no lo tiene
        $progress = $hasBadge ? 100 : $this->calculateBadgeProgress($user, $badge);

        // Obtener usuarios que tienen este badge (top 10)
        $topUsers = \DB::table('user_badges')
            ->join('users', 'user_badges.user_id', '=', 'users.id')
            ->where('badge_id', $badge->id)
            ->select('users.id', 'users.name', 'users.avatar', 'user_badges.earned_at')
            ->orderBy('user_badges.earned_at', 'asc') // Los primeros en obtenerlo
            ->limit(10)
            ->get();

        return response()->json([
            'badge' => $badge,
            'has_badge' => $hasBadge,
            'progress' => $progress,
            'top_users' => $topUsers,
        ]);
    }

    /**
     * Obtener estadísticas de badges del usuario.
     */
    public function stats(Request $request)
    {
        $user = Auth::user();
        $this->badgeService->checkGeneralBadges($user);
        $user = $user->fresh();

        $totalBadges = \DB::table('user_badges')->where('user_id', $user->id)->count();

        $badgesByType = \DB::table('user_badges')
            ->join('badges', 'user_badges.badge_id', '=', 'badges.id')
            ->where('user_badges.user_id', $user->id)
            ->select('badges.type', \DB::raw('count(*) as count'))
            ->groupBy('badges.type')
            ->get();

        $recentBadges = \DB::table('user_badges')
            ->join('badges', 'user_badges.badge_id', '=', 'badges.id')
            ->where('user_badges.user_id', $user->id)
            ->select('badges.*', 'user_badges.earned_at')
            ->orderBy('user_badges.earned_at', 'desc')
            ->limit(5)
            ->get();

        $totalAvailable = Badge::count();
        $percentage = $totalAvailable > 0 ? round(($totalBadges / $totalAvailable) * 100, 1) : 0;

        return response()->json([
            'total_badges' => $totalBadges,
            'total_available' => $totalAvailable,
            'completion_percentage' => $percentage,
            'by_type' => $badgesByType,
            'recent_badges' => $recentBadges,
        ]);
    }

    /**
     * Calcular progreso hacia la obtención de un badge.
     */
    private function calculateBadgeProgress(User $user, Badge $badge): int
    {
        $criteria = $badge->criteria;

        switch ($badge->type) {
            case 'game_master':
                $perfectGames = \DB::table('game_sessions')
                    ->join('game_configurations', 'game_sessions.game_config_id', '=', 'game_configurations.id')
                    ->where('game_sessions.user_id', $user->id)
                    ->where('game_sessions.status', 'completed')
                    ->whereRaw('game_sessions.score = game_configurations.max_score')
                    ->count();
                $target = $criteria['perfect_games'] ?? 20;

                return min(100, intval(($perfectGames / $target) * 100));

            case 'points':
                $current = $user->total_points;
                $target = $criteria['total_points'] ?? 1000;

                return min(100, intval(($current / $target) * 100));

            case 'streak':
                $current = $user->current_streak;
                $target = $criteria['streak_days'] ?? 7;

                return min(100, intval(($current / $target) * 100));

            case 'course_completion':
                $completedCourses = \DB::table('enrollments')
                    ->where('user_id', $user->id)
                    ->where('progress', '>=', 100)
                    ->count();
                $target = $criteria['courses_completed'] ?? 1;

                return min(100, intval(($completedCourses / $target) * 100));

            case 'quiz_expert':
                $perfectQuizzes = \DB::table('user_quiz_attempts')
                    ->where('user_id', $user->id)
                    ->where('status', 'completed')
                    ->where('percentage', 100)
                    ->count();
                $target = $criteria['perfect_quizzes'] ?? 10;

                return min(100, intval(($perfectQuizzes / $target) * 100));

            default:
                return 0;
        }
    }
}
