<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InteractiveConfig;
use App\Models\Lesson;
use App\Models\Purchase;
use App\Models\ShopItem;
use App\Services\ActivityAttemptService;
use App\Services\CourseProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        $premiumItem = $lesson->shopItems()
            ->where('type', 'premium_content')
            ->where('is_active', true)
            ->first();

        if (! $isEnrolled && ! $isOwnerOrAdmin) {
            return response()->json(['message' => 'No tienes acceso a esta lección.'], 403);
        }

        if ($previewMode && ! $isOwnerOrAdmin) {
            return response()->json(['message' => 'Solo el docente dueño puede usar la vista previa.'], 403);
        }

        if ($premiumItem && ! $isOwnerOrAdmin) {
            $hasUnlock = Purchase::query()
                ->where('user_id', $user->id)
                ->where('shop_item_id', $premiumItem->id)
                ->whereIn('status', ['completed', 'consumed'])
                ->exists();

            if (! $hasUnlock) {
                return response()->json([
                    'message' => 'Esta lección bonus requiere desbloqueo con monedas desde la tienda.',
                    'premium_lock' => [
                        'shop_item_id' => $premiumItem->id,
                        'name' => $premiumItem->name,
                        'cost_coins' => $premiumItem->cost_coins,
                    ],
                ], 403);
            }
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
        $commentTarget = $this->resolveCommentTarget($lesson);

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
                'is_premium_bonus' => (bool) $premiumItem,
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
            'comment_target' => $commentTarget,
            'premium_unlock' => $premiumItem ? [
                'id' => $premiumItem->id,
                'name' => $premiumItem->name,
                'cost_coins' => $premiumItem->cost_coins,
                'type' => $premiumItem->type,
            ] : null,
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

        if ($isEnrolled && $progressService->hasBlockingLockedActivity($user, $course, $lesson->id)) {
            $blocking = $progressService->getBlockingLockedActivity($user, $course, $lesson->id);

            return response()->json([
                'message' => 'Tienes una actividad bloqueada por agotar intentos. Necesitas intervención docente para seguir avanzando.',
                'blocking_activity' => [
                    'lesson_id' => $blocking?->lesson_id,
                    'interactive_config_id' => $blocking?->interactive_config_id,
                ],
            ], 423);
        }

        $validated = $request->validate([
            'time_spent_seconds' => 'nullable|integer|min:0|max:86400',
        ]);

        if ($lesson->type !== 'video') {
            return response()->json([
                'message' => 'Solo las lecciones en video afectan el progreso del curso. Los documentos y recursos son de apoyo.',
                'progress' => $progressService->recalculateEnrollmentProgress($user, $course),
            ], 422);
        }

        $progressService->markLessonCompleted($user, $lesson, $validated['time_spent_seconds'] ?? 0);
        $snapshot = $progressService->recalculateEnrollmentProgress($user, $course);

        return response()->json([
            'message' => 'Video marcado como completado.',
            'progress' => $snapshot,
        ]);
    }

    /**
     * Registrar finalización de actividad interactiva (renderer genérico).
     */
    public function completeInteractive(Request $request, Lesson $lesson, CourseProgressService $progressService, ActivityAttemptService $activityAttemptService)
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
            'answers' => 'nullable|array',
            'meta' => 'nullable|array',
        ]);

        if ($isOwnerOrAdmin && ! $isEnrolled) {
            $ratio = min(1, max(0, ((float) $validated['score']) / ((float) $validated['max_score'])));

            return response()->json([
                'message' => 'Vista previa completada. No se alteró el progreso real del curso.',
                'xp_awarded' => (int) round($ratio * ($lesson->interactiveConfig?->xp_reward ?? 100)),
                'coin_awarded' => (int) round($ratio * ($lesson->interactiveConfig?->coin_reward ?? 25)),
                'progress' => $progressService->getProgressSnapshot($user, $course, false),
            ]);
        }

        if (! $lesson->interactiveConfig) {
            return response()->json([
                'message' => 'Esta lección no tiene configuración interactiva activa.',
            ], 422);
        }

        $result = $activityAttemptService->submit(
            $user,
            $lesson->interactiveConfig,
            $validated['score'],
            $validated['max_score'],
            [
                'time_spent_seconds' => $validated['time_spent_seconds'] ?? 0,
                'answers' => $validated['answers'] ?? [],
                'meta' => $validated['meta'] ?? [],
            ]
        );

        return response()->json(
            collect($result)->except(['status_code'])->all(),
            $result['status_code']
        );
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
            'max_attempts' => $interactive->max_attempts,
            'passing_score' => $interactive->passing_score,
            'xp_reward' => $interactive->xp_reward,
            'coin_reward' => $interactive->coin_reward,
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
                    'video_url' => $contentable?->signedStreamUrl() ?? $contentable?->video_url ?? $lesson->content_url,
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
                    'file_url' => $contentable?->signedDownloadUrl() ?? $contentable?->file_url ?? $lesson->content_url,
                    'description' => $contentable?->metadata['description'] ?? $lesson->content_text,
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

    private function resolveCommentTarget(Lesson $lesson): ?array
    {
        if ($lesson->contentable instanceof InteractiveConfig || $lesson->interactiveConfig) {
            $interactive = $lesson->interactiveConfig ?: $lesson->contentable;

            return [
                'type' => $interactive->getMorphClass(),
                'id' => $interactive->getKey(),
            ];
        }

        if (! $lesson->contentable) {
            return null;
        }

        return [
            'type' => $lesson->contentable->getMorphClass(),
            'id' => $lesson->contentable->getKey(),
        ];
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
