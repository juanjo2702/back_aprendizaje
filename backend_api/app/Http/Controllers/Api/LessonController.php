<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InteractiveConfig;
use App\Models\Lesson;
use App\Services\CourseProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LessonController extends Controller
{
    /**
     * Obtener detalles de una lección específica con contenido polimórfico
     * y datos para CoursePlayer (sidebar, progreso, gamificación opcional).
     */
    public function show($lessonId, CourseProgressService $progressService)
    {
        $user = Auth::user();
        $previewMode = request()->boolean('preview');

        $lesson = Lesson::with([
            'contentable',
            'interactiveConfig',
            'gameConfiguration.gameType',
            'quiz.questions',
            'module.course.modules.lessons' => function ($query) {
                $query->orderBy('sort_order');
            },
        ])
            ->findOrFail($lessonId);

        $course = $lesson->module->course;
        $isEnrolled = $course->enrollments()->where('user_id', $user->id)->exists();
        $isOwnerOrAdmin = $user->isAdmin() || ($user->isInstructor() && (int) $course->instructor_id === (int) $user->id);

        if (! $isEnrolled && ! $isOwnerOrAdmin) {
            return response()->json(['message' => 'No tienes acceso a esta lección.'], 403);
        }

        if ($previewMode && ! $isOwnerOrAdmin) {
            return response()->json(['message' => 'Solo el docente dueño puede usar la vista previa.'], 403);
        }

        $progressSnapshot = $progressService->getProgressSnapshot(
            $user,
            $course,
            ! $previewMode || $isEnrolled
        );
        $completedLessons = $user->lessonProgress()
            ->where('course_id', $course->id)
            ->where('is_completed', true)
            ->pluck('lesson_id')
            ->all();

        [$previousLesson, $nextLesson] = $this->resolveAdjacentLessons($lesson, $course);
        $interactiveConfig = $this->resolveInteractiveConfig($lesson);

        return response()->json([
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'type' => $lesson->type,
                'normalized_type' => $lesson->normalized_type,
                'content_url' => $lesson->content_url,
                'content_text' => $lesson->content_text,
                'duration' => $lesson->duration,
                'is_free' => $lesson->is_free,
                'sort_order' => $lesson->sort_order,
                'module_id' => $lesson->module_id,
                'content' => $this->resolveLessonContent($lesson),
            ],
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'status' => $course->status,
                'has_interactive_activities' => $progressSnapshot['has_interactive_activities'],
                'progress' => $progressSnapshot,
                'is_preview' => $previewMode,
            ],
            'sidebar' => [
                'modules' => $course->modules
                    ->sortBy('sort_order')
                    ->values()
                    ->map(function ($module) use ($completedLessons) {
                        return [
                            'id' => $module->id,
                            'title' => $module->title,
                            'sort_order' => $module->sort_order,
                            'lessons' => $module->lessons
                                ->sortBy('sort_order')
                                ->values()
                                ->map(function ($item) use ($completedLessons) {
                                    return [
                                        'id' => $item->id,
                                        'title' => $item->title,
                                        'type' => $item->normalized_type,
                                        'sort_order' => $item->sort_order,
                                        'is_free' => (bool) $item->is_free,
                                        'is_completed' => in_array($item->id, $completedLessons, true),
                                        'is_interactive' => $item->isInteractive(),
                                    ];
                                })
                                ->all(),
                        ];
                    })
                    ->all(),
            ],
            'gamification' => [
                'enabled' => $progressSnapshot['has_interactive_activities'],
                'show_achievements_tab' => $progressSnapshot['has_interactive_activities'],
                'leaderboard' => $progressSnapshot['has_interactive_activities']
                    ? $progressService->getCourseLeaderboard($course, 10)
                    : [],
            ],
            'interactive_config' => $interactiveConfig,
            // Campos legacy conservados para no romper pantallas existentes.
            'game' => $lesson->gameConfiguration ? [
                'id' => $lesson->gameConfiguration->id,
                'title' => $lesson->gameConfiguration->title,
                'game_type' => $lesson->gameConfiguration->gameType->slug ?? $lesson->gameConfiguration->gameType->name,
                'config' => $lesson->gameConfiguration->config,
                'max_score' => $lesson->gameConfiguration->max_score,
                'time_limit' => $lesson->gameConfiguration->time_limit,
                'max_attempts' => $lesson->gameConfiguration->max_attempts,
            ] : null,
            'quiz' => $lesson->quiz ? [
                'id' => $lesson->quiz->id,
                'title' => $lesson->quiz->title,
                'description' => $lesson->quiz->description,
                'questions' => $lesson->quiz->questions->map(function ($question) {
                    return [
                        'id' => $question->id,
                        'text' => $question->question ?? $question->text,
                        'type' => $question->type,
                        'options' => $question->options,
                        'points' => $question->points,
                    ];
                })->values()->all(),
                'time_limit' => $lesson->quiz->time_limit,
                'passing_score' => $lesson->quiz->passing_score,
            ] : null,
            'navigation' => [
                'previous_lesson' => $previousLesson,
                'next_lesson' => $nextLesson,
            ],
        ]);
    }

    /**
     * Marcar una lección como completada.
     */
    public function complete(Request $request, Lesson $lesson, CourseProgressService $progressService)
    {
        $user = $request->user();
        $course = $lesson->module->course;
        $isEnrolled = $course->enrollments()->where('user_id', $user->id)->exists();
        $isOwnerOrAdmin = $user->isAdmin() || ($user->isInstructor() && (int) $course->instructor_id === (int) $user->id);

        if (! $isEnrolled && ! $isOwnerOrAdmin) {
            return response()->json(['message' => 'No tienes acceso a esta lección.'], 403);
        }

        $validated = $request->validate([
            'time_spent_seconds' => 'nullable|integer|min:0|max:86400',
        ]);

        $progressService->markLessonCompleted($user, $lesson, $validated['time_spent_seconds'] ?? 0);
        $snapshot = $progressService->recalculateEnrollmentProgress($user, $course);

        return response()->json([
            'message' => 'Lección marcada como completada.',
            'progress' => $snapshot,
        ]);
    }

    /**
     * Registrar finalización de actividad interactiva (renderer genérico).
     */
    public function completeInteractive(Request $request, Lesson $lesson, CourseProgressService $progressService)
    {
        $user = $request->user();
        $course = $lesson->module->course;
        $isEnrolled = $course->enrollments()->where('user_id', $user->id)->exists();
        $isOwnerOrAdmin = $user->isAdmin() || ($user->isInstructor() && (int) $course->instructor_id === (int) $user->id);

        if (! $isEnrolled && ! $isOwnerOrAdmin) {
            return response()->json(['message' => 'No tienes acceso a esta lección.'], 403);
        }

        $validated = $request->validate([
            'score' => 'required|numeric|min:0',
            'max_score' => 'required|numeric|min:1',
            'time_spent_seconds' => 'nullable|integer|min:0|max:86400',
        ]);

        if (! $progressService->courseHasInteractiveActivities($course)) {
            return response()->json([
                'message' => 'Este curso no tiene actividades interactivas habilitadas.',
            ], 422);
        }

        $progressService->markLessonCompleted($user, $lesson, $validated['time_spent_seconds'] ?? 0);

        $ratio = min(1, max(0, ((float) $validated['score']) / ((float) $validated['max_score'])));
        $xpAwarded = (int) round($ratio * 100);

        if ($xpAwarded > 0) {
            $user->increment('total_points', $xpAwarded);
            DB::table('points_log')->insert([
                'user_id' => $user->id,
                'points' => $xpAwarded,
                'source' => 'interactive_lesson',
                'source_id' => $lesson->id,
                'description' => 'XP por completar actividad interactiva: '.$lesson->title,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $progressService->recordInteractiveCompletion(
            $user,
            $lesson,
            'interactive_renderer',
            (int) $lesson->id,
            (float) $validated['score'],
            (float) $validated['max_score'],
            $xpAwarded
        );

        $snapshot = $progressService->recalculateEnrollmentProgress($user, $course);

        return response()->json([
            'message' => 'Actividad interactiva completada.',
            'xp_awarded' => $xpAwarded,
            'progress' => $snapshot,
        ]);
    }

    private function resolveInteractiveConfig(Lesson $lesson): ?array
    {
        $interactive = $lesson->interactiveConfig;

        if (! $interactive && $lesson->contentable instanceof InteractiveConfig) {
            $interactive = $lesson->contentable;
        }

        if (! $interactive) {
            return null;
        }

        return [
            'id' => $interactive->id,
            'authoring_mode' => $interactive->authoring_mode,
            'activity_type' => $interactive->activity_type,
            'config_payload' => $interactive->config_payload,
            'assets_manifest' => $interactive->assets_manifest,
            'version' => $interactive->version,
            'is_active' => (bool) $interactive->is_active,
        ];
    }

    private function resolveLessonContent(Lesson $lesson): array
    {
        $type = $lesson->normalized_type;
        $contentable = $lesson->contentable;

        if ($contentable && $contentable instanceof InteractiveConfig) {
            return [
                'kind' => 'interactive',
                'payload' => [
                    'id' => $contentable->id,
                    'authoring_mode' => $contentable->authoring_mode,
                    'activity_type' => $contentable->activity_type,
                    'config_payload' => $contentable->config_payload,
                    'assets_manifest' => $contentable->assets_manifest,
                ],
            ];
        }

        return match ($type) {
            'video' => [
                'kind' => 'video',
                'payload' => [
                    'video_url' => $contentable?->video_url ?? $lesson->content_url,
                    'embed_url' => $contentable?->embed_url,
                    'provider' => $contentable?->provider,
                    'duration_seconds' => $contentable?->duration_seconds ?? $lesson->duration,
                ],
            ],
            'reading' => [
                'kind' => 'reading',
                'payload' => [
                    'body_markdown' => $contentable?->body_markdown ?? $lesson->content_text,
                    'body_html' => $contentable?->body_html,
                    'estimated_minutes' => $contentable?->estimated_minutes,
                ],
            ],
            'resource' => [
                'kind' => 'resource',
                'payload' => [
                    'file_name' => $contentable?->file_name,
                    'file_url' => $contentable?->file_url ?? $lesson->content_url,
                    'mime_type' => $contentable?->mime_type,
                    'file_size_bytes' => $contentable?->file_size_bytes,
                    'is_downloadable' => (bool) ($contentable?->is_downloadable ?? true),
                ],
            ],
            default => [
                'kind' => $type,
                'payload' => [],
            ],
        };
    }

    private function resolveAdjacentLessons(Lesson $lesson, $course): array
    {
        $orderedLessons = $course->modules
            ->sortBy('sort_order')
            ->flatMap(function ($module) {
                return $module->lessons->sortBy('sort_order');
            })
            ->values();

        $index = $orderedLessons->search(fn ($item) => (int) $item->id === (int) $lesson->id);
        if ($index === false) {
            return [null, null];
        }

        $previous = $index > 0 ? $orderedLessons[$index - 1] : null;
        $next = $index < ($orderedLessons->count() - 1) ? $orderedLessons[$index + 1] : null;

        return [
            $previous ? ['id' => $previous->id, 'title' => $previous->title] : null,
            $next ? ['id' => $next->id, 'title' => $next->title] : null,
        ];
    }
}
