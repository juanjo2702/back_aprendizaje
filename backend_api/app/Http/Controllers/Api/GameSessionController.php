<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameConfiguration;
use App\Models\GameSession;
use App\Models\User;
use App\Services\CourseProgressService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GameSessionController extends Controller
{
    /**
     * Listar sesiones de juego del usuario autenticado.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = GameSession::where('user_id', $user->id)
            ->with([
                'gameConfiguration:id,title,game_type_id,max_score,time_limit',
                'gameConfiguration.gameType:id,name,slug',
                'course:id,title,slug',
                'module:id,title',
                'lesson:id,title',
            ])
            ->orderBy('created_at', 'desc');

        // Filtrar por curso
        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        // Filtrar por estado
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $query->paginate($request->get('per_page', 15));
    }

    /**
     * Iniciar una nueva sesión de juego.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'game_config_id' => 'required|exists:game_configurations,id',
        ]);

        $gameConfig = GameConfiguration::with(['course', 'module', 'lesson'])
            ->where('is_active', true)
            ->findOrFail($validated['game_config_id']);

        // Verificar límite de intentos
        $attemptsCount = GameSession::where('user_id', $user->id)
            ->where('game_config_id', $gameConfig->id)
            ->where('status', 'completed')
            ->count();

        if ($gameConfig->max_attempts > 0 && $attemptsCount >= $gameConfig->max_attempts) {
            return response()->json([
                'message' => 'Has alcanzado el límite de intentos para este juego.',
            ], 422);
        }

        // Crear sesión
        $session = GameSession::create([
            'user_id' => $user->id,
            'game_config_id' => $gameConfig->id,
            'course_id' => $gameConfig->course_id,
            'module_id' => $gameConfig->module_id,
            'lesson_id' => $gameConfig->lesson_id,
            'score' => 0,
            'time_spent' => 0,
            'status' => 'started',
            'started_at' => Carbon::now(),
        ]);

        return response()->json($session->load([
            'gameConfiguration:id,title,game_type_id,max_score,time_limit,config',
            'gameConfiguration.gameType:id,name,slug,default_config',
        ]), 201);
    }

    /**
     * Actualizar sesión (marcar como completada, actualizar puntaje).
     */
    public function update(Request $request, GameSession $gameSession)
    {
        $user = Auth::user();
        $progressService = app(CourseProgressService::class);

        // Verificar que la sesión pertenezca al usuario
        if ($gameSession->user_id !== $user->id) {
            abort(403, 'Esta sesión no te pertenece.');
        }

        // Verificar que la sesión no esté ya completada
        if ($gameSession->status === 'completed') {
            return response()->json([
                'message' => 'Esta sesión ya fue completada.',
            ], 422);
        }

        $validated = $request->validate([
            'score' => 'required|integer|min:0|max:'.$gameSession->gameConfiguration->max_score,
            'time_spent' => 'required|integer|min:1',
            'details' => 'nullable|array',
        ]);

        DB::transaction(function () use ($gameSession, $validated, $user, $progressService) {
            // Actualizar sesión
            $gameSession->update([
                'score' => $validated['score'],
                'time_spent' => $validated['time_spent'],
                'details' => $validated['details'] ?? null,
                'status' => 'completed',
                'completed_at' => Carbon::now(),
            ]);

            $gameSession->loadMissing(['course', 'lesson', 'gameConfiguration.lesson']);
            $course = $gameSession->course ?: $gameSession->gameConfiguration?->course;
            $lesson = $gameSession->lesson ?: $gameSession->gameConfiguration?->lesson;

            if ($lesson) {
                $progressService->markLessonCompleted($user, $lesson, (int) $validated['time_spent']);
            }

            $gamificationEnabled = $course ? $progressService->courseHasInteractiveActivities($course) : false;
            $totalPoints = 0;

            if ($gamificationEnabled) {
                // Calcular puntos ganados (proporcionales al puntaje)
                $maxScore = max(1, (int) $gameSession->gameConfiguration->max_score);
                $scorePercentage = ($validated['score'] / $maxScore) * 100;

                // Base points: 10% del puntaje máximo
                $basePoints = intval($maxScore * 0.1);
                // Bonus por puntaje alto
                $bonusPoints = $scorePercentage >= 90 ? 50 : ($scorePercentage >= 70 ? 25 : 0);
                $totalPoints = $basePoints + $bonusPoints;

                // Actualizar puntos del usuario
                $user->total_points += $totalPoints;
                $user->save();

                DB::table('points_log')->insert([
                    'user_id' => $user->id,
                    'points' => $totalPoints,
                    'source' => 'game_session',
                    'source_id' => $gameSession->id,
                    'description' => 'Puntos por completar juego: '.$gameSession->gameConfiguration->title,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                // Verificar si se ganó algún badge
                $this->checkForBadges($user, $gameSession);
            }

            if ($gamificationEnabled && $lesson) {
                $progressService->recordInteractiveCompletion(
                    $user,
                    $lesson,
                    'game_session',
                    (int) $gameSession->id,
                    (float) $validated['score'],
                    (float) $gameSession->gameConfiguration->max_score,
                    $totalPoints
                );
            }

            if ($course) {
                $progressService->recalculateEnrollmentProgress($user, $course);
            }
        });

        return response()->json($gameSession->fresh()->load([
            'gameConfiguration',
            'gameConfiguration.gameType',
            'course',
            'module',
            'lesson',
        ]));
    }

    /**
     * Mostrar estadísticas de juegos del usuario.
     */
    public function stats(Request $request)
    {
        $user = Auth::user();

        $totalSessions = GameSession::where('user_id', $user->id)
            ->where('status', 'completed')
            ->count();

        $totalScore = GameSession::where('user_id', $user->id)
            ->where('status', 'completed')
            ->sum('score');

        $avgScore = $totalSessions > 0 ? $totalScore / $totalSessions : 0;

        $perfectGames = GameSession::where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereRaw('score = game_configurations.max_score')
            ->join('game_configurations', 'game_sessions.game_config_id', '=', 'game_configurations.id')
            ->count();

        $recentSessions = GameSession::where('user_id', $user->id)
            ->where('status', 'completed')
            ->with(['gameConfiguration.gameType', 'course'])
            ->orderBy('completed_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'total_sessions' => $totalSessions,
            'total_score' => $totalScore,
            'average_score' => round($avgScore, 2),
            'perfect_games' => $perfectGames,
            'recent_sessions' => $recentSessions,
        ]);
    }

    /**
     * Helper: verificar badges ganados después de una sesión.
     */
    private function checkForBadges(User $user, GameSession $session)
    {
        $badgeService = new \App\Services\BadgeService;
        $badgeService->checkGameBadges($user, $session);
    }
}
