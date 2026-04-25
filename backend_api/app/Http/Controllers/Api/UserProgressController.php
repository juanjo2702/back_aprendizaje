<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GameSession;
use App\Models\InteractiveActivityResult;
use App\Models\Purchase;
use App\Models\User;
use App\Models\UserLessonProgress;
use App\Models\UserQuizAttempt;
use App\Services\BadgeService;
use App\Services\CourseProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserProgressController extends Controller
{
    public function __construct(
        private readonly BadgeService $badgeService
    ) {
    }

    /**
     * Obtener estadísticas generales del usuario para el dashboard.
     */
    public function dashboardStats(Request $request)
    {
        $user = Auth::user();
        $this->badgeService->checkGeneralBadges($user);
        $user = $user->fresh();

        // Cursos del usuario
        $enrollments = $user->enrollments()->with(['course.instructor:id,name', 'course.category:id,name,slug'])->get();
        $totalCourses = $enrollments->count();
        $completedCourses = $enrollments->where('progress', '>=', 100)->count();
        $inProgressCourses = $enrollments->where('progress', '>', 0)->where('progress', '<', 100)->count();
        $recentCourses = $enrollments->sortByDesc('updated_at')->take(3)->values();

        // Actividades recientes (para sección separada)
        $recentGames = $user->gameSessions()
            ->where('status', 'completed')
            ->with('gameConfiguration:id,title')
            ->orderBy('completed_at', 'desc')
            ->limit(5)
            ->get();

        $recentQuizzes = $user->quizAttempts()
            ->where('status', 'completed')
            ->with('quiz:id,title')
            ->orderBy('completed_at', 'desc')
            ->limit(5)
            ->get();

        $recentCertificates = $user->certificates()
            ->with('course:id,title')
            ->orderBy('issued_at', 'desc')
            ->limit(5)
            ->get();

        // Logros
        $totalBadges = $user->badges()->count();
        $totalCertificates = $user->certificates()->count();
        $totalGamesCompleted = $user->gameSessions()->where('status', 'completed')->count();
        $totalQuizzesCompleted = $user->quizAttempts()->where('status', 'completed')->count();
        $recentPurchases = Purchase::query()
            ->where('user_id', $user->id)
            ->with('shopItem:id,name,type')
            ->latest('purchased_at')
            ->limit(4)
            ->get();

        return response()->json([
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'stats' => [
                'total_points' => $user->total_points,
                'current_level' => $user->current_level,
                'level_title' => $user->level_title,
                'current_streak' => $user->current_streak,
                'last_active_at' => $user->last_active_at,
                'earned_coins' => $user->earned_coins,
                'spent_coins' => $user->spent_coins,
                'available_coins' => $user->available_coins,
                'completed_courses' => $completedCourses,
                'in_progress_courses' => $inProgressCourses,
                'total_badges' => $totalBadges,
                'total_certificates' => $totalCertificates,
                'points_this_month' => 0, // TODO: implementar con PointsLog
            ],
            'courses' => [
                'total' => $totalCourses,
                'completed' => $completedCourses,
                'in_progress' => $inProgressCourses,
                'recent' => $recentCourses->map(function ($enrollment) {
                    return [
                        'id' => $enrollment->id,
                        'progress' => $enrollment->progress,
                        'course' => [
                            'id' => $enrollment->course->id,
                            'slug' => $enrollment->course->slug,
                            'title' => $enrollment->course->title,
                            'category' => $enrollment->course->category ? [
                                'id' => $enrollment->course->category->id,
                                'name' => $enrollment->course->category->name,
                                'slug' => $enrollment->course->category->slug,
                            ] : null,
                            'instructor' => $enrollment->course->instructor ? ['name' => $enrollment->course->instructor->name] : null,
                        ],
                    ];
                })->toArray(),
            ],
            'activities' => [
                'recent_games' => $recentGames->map(function ($session) {
                    return [
                        'id' => $session->id,
                        'title' => $session->gameConfiguration->title,
                        'score' => $session->score,
                        'max_score' => $session->gameConfiguration->max_score,
                        'completed_at' => $session->completed_at,
                    ];
                })->toArray(),
                'recent_quizzes' => $recentQuizzes->map(function ($attempt) {
                    return [
                        'id' => $attempt->id,
                        'title' => $attempt->quiz->title,
                        'score_percentage' => $attempt->percentage,
                        'completed_at' => $attempt->completed_at,
                    ];
                })->toArray(),
                'recent_certificates' => $recentCertificates->map(function ($cert) {
                    return [
                        'id' => $cert->id,
                        'course_title' => $cert->course->title,
                        'certificate_code' => $cert->certificate_code,
                        'issued_at' => $cert->issued_at,
                    ];
                })->toArray(),
                'recent_purchases' => $recentPurchases->map(function (Purchase $purchase) {
                    return [
                        'id' => $purchase->id,
                        'status' => $purchase->status,
                        'cost_coins' => $purchase->cost_coins,
                        'purchased_at' => $purchase->purchased_at,
                        'shop_item' => $purchase->shopItem ? [
                            'name' => $purchase->shopItem->name,
                            'type' => $purchase->shopItem->type,
                        ] : null,
                    ];
                })->toArray(),
            ],
            'achievements' => [
                'total_badges' => $totalBadges,
                'total_certificates' => $totalCertificates,
                'total_games_completed' => $totalGamesCompleted,
                'total_quizzes_completed' => $totalQuizzesCompleted,
            ],
        ]);
    }

    /**
     * Obtener cursos del usuario con progreso detallado.
     */
    public function userCourses(Request $request)
    {
        $user = Auth::user();

        $query = Enrollment::where('user_id', $user->id)
            ->with([
                'course:id,title,slug,thumbnail,level,language,instructor_id,category_id',
                'course.instructor:id,name,avatar',
                'course.category:id,name,slug',
            ]);

        // Filtrar por estado de progreso
        if ($request->filled('status')) {
            switch ($request->status) {
                case 'completed':
                    $query->where('progress', '>=', 100);
                    break;
                case 'in_progress':
                    $query->where('progress', '>', 0)->where('progress', '<', 100);
                    break;
                case 'not_started':
                    $query->where('progress', 0);
                    break;
            }
        }

        // Ordenar
        $sort = $request->get('sort', 'recent');
        switch ($sort) {
            case 'progress':
                $query->orderBy('progress', 'desc');
                break;
            case 'title':
                $query->join('courses', 'enrollments.course_id', '=', 'courses.id')
                    ->orderBy('courses.title');
                break;
            default: // recent
                $query->orderBy('updated_at', 'desc');
        }

        return $query->paginate($request->get('per_page', 12));
    }

    /**
     * Obtener progreso detallado de un curso específico.
     */
    public function courseProgress(Request $request, Course $course, CourseProgressService $progressService)
    {
        $user = Auth::user();

        // Verificar que el usuario esté inscrito
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->firstOrFail();

        $progressSnapshot = $progressService->recalculateEnrollmentProgress($user, $course);

        // Cargar módulos y lecciones
        $course->load([
            'modules' => function ($query) {
                $query->with([
                    'lessons' => function ($q) {
                        $q->with(['interactiveConfig', 'gameConfiguration', 'quiz'])->orderBy('sort_order');
                    },
                ]);
                $query->orderBy('sort_order');
            },
            'quizzes' => function ($query) use ($user) {
                $query->withCount(['attempts' => function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                }]);
            },
        ]);

        $completedLessonIds = UserLessonProgress::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('is_completed', true)
            ->pluck('lesson_id')
            ->all();

        $completedInteractiveIds = InteractiveActivityResult::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', 'completed')
            ->pluck('lesson_id')
            ->unique()
            ->all();

        // Calcular progreso por módulo
        $modulesProgress = [];
        $totalLessons = 0;
        $completedLessonsCount = 0;

        foreach ($course->modules as $module) {
            $moduleLessons = $module->lessons->count();
            $moduleLessonIds = $module->lessons->pluck('id')->all();
            $completedModuleLessons = count(array_intersect($moduleLessonIds, $completedLessonIds));
            $interactiveLessonIds = $module->lessons
                ->filter(fn ($lesson) => in_array($lesson->normalized_type, ['interactive', 'game', 'quiz'], true))
                ->pluck('id')
                ->all();
            $completedInteractiveByModule = count(array_intersect($interactiveLessonIds, $completedInteractiveIds));

            $modulesProgress[] = [
                'module_id' => $module->id,
                'title' => $module->title,
                'total_lessons' => $moduleLessons,
                'completed_lessons' => $completedModuleLessons,
                'interactive_total' => count($interactiveLessonIds),
                'interactive_completed' => $completedInteractiveByModule,
                'progress' => $moduleLessons > 0 ? round(($completedModuleLessons / $moduleLessons) * 100) : 0,
            ];

            $totalLessons += $moduleLessons;
            $completedLessonsCount += $completedModuleLessons;
        }

        // Intentos de quiz en este curso
        $quizAttempts = UserQuizAttempt::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', 'completed')
            ->with(['quiz:id,title'])
            ->orderBy('completed_at', 'desc')
            ->limit(10)
            ->get();

        // Sesiones de juego en este curso
        $gameSessions = GameSession::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', 'completed')
            ->with(['gameConfiguration:id,title,game_type_id', 'gameConfiguration.gameType:id,name'])
            ->orderBy('completed_at', 'desc')
            ->limit(10)
            ->get();

        // Verificar si califica para certificado
        $qualifiesForCertificate = false;
        $certificateMessage = null;
        $finalExam = $this->resolveCertificateFinalExam($user, $course);

        if ($course->has_certificate) {
            if ($enrollment->progress >= 100) {
                if ($course->certificate_requires_final_exam) {
                    if (! $finalExam['lesson']) {
                        $certificateMessage = 'El docente todavía no configuró la evaluación final para este certificado.';
                    } elseif (! $finalExam['result']) {
                        $certificateMessage = "Debes aprobar el examen final \"{$finalExam['lesson']->title}\" para obtener el certificado.";
                    } else {
                        $qualifiesForCertificate = $finalExam['passed'];
                        $certificateMessage = $qualifiesForCertificate
                            ? "Aprobaste el examen final \"{$finalExam['lesson']->title}\" y ya calificas para el certificado."
                            : "Necesitas {$course->certificate_min_score}% en el examen final \"{$finalExam['lesson']->title}\". Tu mejor puntaje actual es ".round($finalExam['score_percentage'], 2).'%.';
                    }
                } elseif ($course->certificate_min_score > 0) {
                    $avgScore = $this->calculateCourseAverage($user, $course);
                    $qualifiesForCertificate = $avgScore >= $course->certificate_min_score;
                    $certificateMessage = $qualifiesForCertificate
                        ? '¡Calificas para el certificado!'
                        : "Necesitas un puntaje mínimo de {$course->certificate_min_score}%. Tu promedio: ".round($avgScore, 2).'%';
                } else {
                    $qualifiesForCertificate = true;
                    $certificateMessage = '¡Calificas para el certificado!';
                }
            } else {
                $certificateMessage = 'Completa el curso al 100% para calificar.';
            }
        }

        return response()->json([
            'course' => $course->only(['id', 'title', 'slug', 'thumbnail', 'level', 'has_certificate', 'certificate_min_score']),
            'enrollment' => $enrollment,
            'modules_progress' => $modulesProgress,
            'overall_progress' => [
                'total_lessons' => $totalLessons,
                'completed_lessons' => $completedLessonsCount,
                'percentage' => $progressSnapshot['overall_progress'],
                'lessons_percentage' => $progressSnapshot['videos']['progress'],
                'interactive_percentage' => $progressSnapshot['interactive']['progress'],
            ],
            'quizzes' => [
                'total' => $course->quizzes->count(),
                'attempts' => $quizAttempts,
                'average_score' => $quizAttempts->avg('percentage') ?? 0,
            ],
            'games' => [
                'sessions' => $gameSessions,
                'total_completed' => $gameSessions->count(),
                'average_score' => $gameSessions->avg('score') ?? 0,
            ],
            'gamification' => [
                'enabled' => $progressSnapshot['has_interactive_activities'],
                'show_achievements_tab' => $progressSnapshot['has_interactive_activities'],
                'leaderboard' => $progressSnapshot['has_interactive_activities']
                    ? $progressService->getCourseLeaderboard($course, 10)
                    : [],
            ],
            'certificate' => [
                'available' => $course->has_certificate,
                'qualifies' => $qualifiesForCertificate,
                'message' => $certificateMessage,
                'requires_final_exam' => (bool) $course->certificate_requires_final_exam,
                'required_score' => (int) $course->certificate_min_score,
                'final_exam' => [
                    'lesson' => $finalExam['lesson'] ? [
                        'id' => $finalExam['lesson']->id,
                        'title' => $finalExam['lesson']->title,
                        'module_title' => $finalExam['lesson']->module?->title,
                    ] : null,
                    'result' => $finalExam['result'] ? [
                        'status' => $finalExam['result']->status,
                        'attempts_used' => (int) $finalExam['result']->attempts_used,
                        'score_percentage' => round($finalExam['score_percentage'], 2),
                        'completed_at' => $finalExam['result']->completed_at,
                    ] : null,
                ],
                'existing' => Certificate::where('user_id', $user->id)->where('course_id', $course->id)->first(),
            ],
        ]);
    }

    /**
     * Obtener actividad reciente del usuario (juegos, quizzes, certificados).
     */
    public function recentActivity(Request $request)
    {
        $user = Auth::user();
        $limit = $request->get('limit', 20);

        // Juegos completados
        $games = $user->gameSessions()
            ->where('status', 'completed')
            ->with(['gameConfiguration:id,title,game_type_id', 'course:id,title'])
            ->orderBy('completed_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'type' => 'game',
                    'title' => $session->gameConfiguration->title,
                    'score' => $session->score,
                    'max_score' => $session->gameConfiguration->max_score,
                    'time_spent' => $session->time_spent,
                    'course' => $session->course ? [
                        'id' => $session->course->id,
                        'title' => $session->course->title,
                    ] : null,
                    'date' => $session->completed_at,
                    'icon' => 'mdi-gamepad-variant',
                    'color' => 'purple',
                ];
            });

        // Quizzes completados
        $quizzes = $user->quizAttempts()
            ->where('status', 'completed')
            ->with(['quiz:id,title', 'course:id,title'])
            ->orderBy('completed_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($attempt) {
                return [
                    'id' => $attempt->id,
                    'type' => 'quiz',
                    'title' => $attempt->quiz->title,
                    'score' => $attempt->percentage,
                    'percentage' => $attempt->percentage,
                    'correct_answers' => $attempt->correct_answers,
                    'total_questions' => $attempt->total_questions,
                    'time_spent' => $attempt->time_spent,
                    'course' => $attempt->course ? [
                        'id' => $attempt->course->id,
                        'title' => $attempt->course->title,
                    ] : null,
                    'date' => $attempt->completed_at,
                    'icon' => 'mdi-format-list-checks',
                    'color' => 'blue',
                ];
            });

        // Certificados obtenidos
        $certificates = $user->certificates()
            ->with('course:id,title')
            ->orderBy('issued_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($cert) {
                return [
                    'id' => $cert->id,
                    'type' => 'certificate',
                    'title' => 'Certificado obtenido: '.$cert->course->title,
                    'certificate_code' => $cert->certificate_code,
                    'course' => [
                        'id' => $cert->course->id,
                        'title' => $cert->course->title,
                    ],
                    'date' => $cert->issued_at,
                    'icon' => 'mdi-certificate',
                    'color' => 'green',
                ];
            });

        // Combinar y ordenar por fecha descendente
        $activities = collect()
            ->merge($games)
            ->merge($quizzes)
            ->merge($certificates)
            ->sortByDesc('date')
            ->take($limit)
            ->values()
            ->toArray();

        return response()->json([
            'total_activities' => count($activities),
            'activities' => $activities,
        ]);
    }

    /**
     * Calcular promedio de score del usuario en un curso.
     */
    private function calculateCourseAverage(User $user, Course $course): float
    {
        $avg = DB::table('user_quiz_attempts')
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', 'completed')
            ->avg('percentage');

        return $avg ? (float) $avg : 0.0;
    }

    private function resolveCertificateFinalExam(User $user, Course $course): array
    {
        $lesson = $course->certificateFinalLesson()
            ->with('module:id,title')
            ->first();

        $result = null;
        $scorePercentage = null;
        $passed = false;

        if ($lesson) {
            $result = InteractiveActivityResult::query()
                ->where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->where('lesson_id', $lesson->id)
                ->latest('last_attempt_at')
                ->first();

            if ($result) {
                $scorePercentage = ((float) $result->score / max(1, (float) $result->max_score)) * 100;
                $passed = $result->status === 'completed' && $scorePercentage >= (int) $course->certificate_min_score;
            }
        }

        return [
            'lesson' => $lesson,
            'result' => $result,
            'score_percentage' => $scorePercentage,
            'passed' => $passed,
        ];
    }
}
