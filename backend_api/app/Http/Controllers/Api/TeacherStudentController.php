<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Comment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\InteractiveActivityResult;
use App\Models\InteractiveConfig;
use App\Models\LessonReading;
use App\Models\LessonResource;
use App\Models\LessonVideo;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserLessonProgress;
use App\Models\UserQuizAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TeacherStudentController extends Controller
{
    public function index(Request $request, Course $course)
    {
        $this->authorizeCourseManager($request->user(), $course);

        $search = trim((string) $request->get('search', ''));

        $enrollments = Enrollment::query()
            ->where('course_id', $course->id)
            ->with('user:id,name,email,avatar,last_active_at,total_points')
            ->get()
            ->filter(function (Enrollment $enrollment) use ($search) {
                if ($search === '') {
                    return true;
                }

                return str_contains(mb_strtolower($enrollment->user->name), mb_strtolower($search))
                    || str_contains(mb_strtolower($enrollment->user->email), mb_strtolower($search));
            })
            ->values()
            ->map(function (Enrollment $enrollment) use ($course) {
                $userId = $enrollment->user_id;
                $averageInteractive = (float) InteractiveActivityResult::query()
                    ->where('user_id', $userId)
                    ->where('course_id', $course->id)
                    ->avg('score');
                $averageQuiz = (float) UserQuizAttempt::query()
                    ->where('user_id', $userId)
                    ->where('course_id', $course->id)
                    ->where('status', 'completed')
                    ->avg('percentage');

                $combinedAverage = collect([$averageInteractive ?: null, $averageQuiz ?: null])->filter()->avg() ?? 0;

                $lastLessonActivity = UserLessonProgress::query()
                    ->where('user_id', $userId)
                    ->where('course_id', $course->id)
                    ->max('updated_at');
                $lastInteractiveActivity = InteractiveActivityResult::query()
                    ->where('user_id', $userId)
                    ->where('course_id', $course->id)
                    ->max('completed_at');
                $lastQuizActivity = UserQuizAttempt::query()
                    ->where('user_id', $userId)
                    ->where('course_id', $course->id)
                    ->max('completed_at');

                $lastActivity = collect([$lastLessonActivity, $lastInteractiveActivity, $lastQuizActivity])
                    ->filter()
                    ->map(fn ($value) => Carbon::parse($value))
                    ->sortDesc()
                    ->first();

                $failedActivitiesCount = InteractiveActivityResult::query()
                    ->where('user_id', $userId)
                    ->where('course_id', $course->id)
                    ->where('status', 'failed')
                    ->count();

                $alertIndex = $this->buildAlertIndex($lastActivity, $failedActivitiesCount);

                return [
                    'id' => $enrollment->user->id,
                    'student' => [
                        'id' => $enrollment->user->id,
                        'name' => $enrollment->user->name,
                        'email' => $enrollment->user->email,
                        'avatar' => $enrollment->user->avatar,
                        'level' => $enrollment->user->current_level,
                        'level_title' => $enrollment->user->level_title,
                    ],
                    'progress' => (float) $enrollment->progress,
                    'average_activity_score' => round($combinedAverage, 2),
                    'last_activity_at' => $lastActivity,
                    'failed_activities_count' => $failedActivitiesCount,
                    'alert_index' => $alertIndex,
                ];
            });

        return response()->json([
            'course' => $course->only(['id', 'title', 'slug']),
            'students' => $enrollments,
        ]);
    }

    public function show(Request $request, Course $course, User $student)
    {
        $this->authorizeCourseManager($request->user(), $course);

        $enrollment = Enrollment::query()
            ->where('course_id', $course->id)
            ->where('user_id', $student->id)
            ->firstOrFail();

        $lessonProgress = UserLessonProgress::query()
            ->where('user_id', $student->id)
            ->where('course_id', $course->id)
            ->with(['lesson:id,title,type,duration,module_id', 'lesson.module:id,title'])
            ->orderByDesc('updated_at')
            ->get();

        $failedQuestions = UserAnswer::query()
            ->where('is_correct', false)
            ->whereHas('attempt', function ($query) use ($student, $course) {
                $query->where('user_id', $student->id)
                    ->where('course_id', $course->id);
            })
            ->with([
                'attempt:id,quiz_id,completed_at,percentage',
                'attempt.quiz:id,title',
                'question:id,question,explanation',
            ])
            ->latest()
            ->limit(12)
            ->get()
            ->map(function (UserAnswer $answer) {
                return [
                    'quiz_title' => $answer->attempt?->quiz?->title,
                    'question' => $answer->question?->question,
                    'student_answer' => $answer->user_answer,
                    'completed_at' => $answer->attempt?->completed_at,
                    'attempt_percentage' => $answer->attempt?->percentage,
                    'explanation' => $answer->question?->explanation,
                ];
            });

        $failedActivities = InteractiveActivityResult::query()
            ->where('user_id', $student->id)
            ->where('course_id', $course->id)
            ->where(function ($query) {
                $query->where('status', 'failed')
                    ->orWhereRaw('(score / NULLIF(max_score, 0)) < 0.7');
            })
            ->with('lesson:id,title,module_id', 'lesson.module:id,title')
            ->latest('completed_at')
            ->limit(12)
            ->get()
            ->map(function (InteractiveActivityResult $result) {
                return [
                    'lesson_title' => $result->lesson?->title,
                    'module_title' => $result->lesson?->module?->title,
                    'score' => (float) $result->score,
                    'max_score' => (float) $result->max_score,
                    'completed_at' => $result->completed_at,
                    'status' => $result->status,
                ];
            });

        $recentQuestions = Comment::query()
            ->where('user_id', $student->id)
            ->whereNull('parent_id')
            ->where('is_question', true)
            ->where(function ($query) use ($course) {
                $query->whereHasMorph(
                    'commentable',
                    [LessonVideo::class, LessonReading::class, LessonResource::class, InteractiveConfig::class],
                    function ($morphQuery, $type) use ($course) {
                        if ($type === InteractiveConfig::class) {
                            $morphQuery->where('course_id', $course->id);
                        } else {
                            $morphQuery->whereHas('lesson.module', fn ($moduleQuery) => $moduleQuery->where('course_id', $course->id));
                        }
                    }
                );
            })
            ->withCount('replies')
            ->latest()
            ->limit(10)
            ->get(['id', 'body', 'created_at', 'resolved_at']);

        return response()->json([
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
                'avatar' => $student->avatar,
                'level' => $student->current_level,
                'level_title' => $student->level_title,
                'last_active_at' => $student->last_active_at,
            ],
            'course' => $course->only(['id', 'title', 'slug']),
            'enrollment' => [
                'progress' => (float) $enrollment->progress,
                'enrolled_at' => $enrollment->enrolled_at,
            ],
            'videos_and_lessons' => $lessonProgress->map(function (UserLessonProgress $progress) {
                $watchRatio = $progress->lesson?->duration
                    ? min(100, round(($progress->time_spent_seconds / max(1, $progress->lesson->duration)) * 100))
                    : 0;

                return [
                    'lesson_title' => $progress->lesson?->title,
                    'lesson_type' => $progress->lesson?->type,
                    'module_title' => $progress->lesson?->module?->title,
                    'time_spent_seconds' => $progress->time_spent_seconds,
                    'watch_ratio' => $watchRatio,
                    'started_at' => $progress->started_at,
                    'completed_at' => $progress->completed_at,
                    'is_completed' => (bool) $progress->is_completed,
                ];
            }),
            'failed_questions' => $failedQuestions,
            'failed_activities' => $failedActivities,
            'activity_attempts' => ActivityLog::query()
                ->where('user_id', $student->id)
                ->where('course_id', $course->id)
                ->with(['lesson:id,title,module_id', 'lesson.module:id,title'])
                ->latest('attempted_at')
                ->limit(20)
                ->get()
                ->map(function (ActivityLog $log) {
                    return [
                        'lesson_title' => $log->lesson?->title,
                        'module_title' => $log->lesson?->module?->title,
                        'attempt_number' => $log->attempt_number,
                        'score' => $log->score,
                        'passing_score' => $log->passing_score,
                        'xp_awarded' => $log->xp_awarded,
                        'coin_awarded' => $log->coin_awarded,
                        'status' => $log->status,
                        'attempted_at' => $log->attempted_at,
                    ];
                }),
            'recent_questions' => $recentQuestions,
        ]);
    }

    public function gradebook(Request $request, Course $course)
    {
        $this->authorizeCourseManager($request->user(), $course);

        $interactiveConfigs = InteractiveConfig::query()
            ->where('course_id', $course->id)
            ->where('is_active', true)
            ->with(['lesson:id,title,module_id', 'lesson.module:id,title'])
            ->orderBy('lesson_id')
            ->get();

        $selectedConfigId = $request->integer('interactive_config_id') ?: $interactiveConfigs->first()?->id;
        $selectedConfig = $interactiveConfigs->firstWhere('id', $selectedConfigId);

        $rows = Enrollment::query()
            ->where('course_id', $course->id)
            ->with('user:id,name,email,avatar')
            ->get()
            ->map(function (Enrollment $enrollment) use ($course, $selectedConfigId) {
                $result = InteractiveActivityResult::query()
                    ->where('user_id', $enrollment->user_id)
                    ->where('course_id', $course->id)
                    ->where('interactive_config_id', $selectedConfigId)
                    ->first();

                $logs = ActivityLog::query()
                    ->where('user_id', $enrollment->user_id)
                    ->where('course_id', $course->id)
                    ->where('interactive_config_id', $selectedConfigId)
                    ->orderBy('attempt_number')
                    ->get();

                return [
                    'id' => $enrollment->user->id,
                    'student' => [
                        'id' => $enrollment->user->id,
                        'name' => $enrollment->user->name,
                        'email' => $enrollment->user->email,
                        'avatar' => $enrollment->user->avatar,
                    ],
                    'progress' => (float) $enrollment->progress,
                    'attempts_used' => (int) ($result?->attempts_used ?? $logs->count()),
                    'best_score' => (int) ($logs->max('score') ?? 0),
                    'last_score' => (int) ($logs->last()?->score ?? 0),
                    'average_score' => round((float) ($logs->avg('score') ?? 0), 2),
                    'xp_awarded' => (int) ($result?->xp_awarded ?? 0),
                    'coin_awarded' => (int) ($result?->coin_awarded ?? 0),
                    'status' => $result?->status ?? ($logs->isEmpty() ? 'pending' : 'failed'),
                    'is_locked' => (bool) ($result?->is_locked ?? false),
                    'last_activity_at' => $logs->last()?->attempted_at,
                ];
            })
            ->values();

        return response()->json([
            'course' => $course->only(['id', 'title', 'slug']),
            'activities' => $interactiveConfigs->map(function (InteractiveConfig $config) {
                return [
                    'id' => $config->id,
                    'label' => $config->lesson?->title ?: 'Actividad sin lección',
                    'module_title' => $config->lesson?->module?->title,
                    'activity_type' => $config->activity_type,
                    'max_attempts' => $config->max_attempts,
                    'passing_score' => $config->passing_score,
                    'xp_reward' => $config->xp_reward,
                    'coin_reward' => $config->coin_reward,
                ];
            })->values(),
            'selected_activity' => $selectedConfig ? [
                'id' => $selectedConfig->id,
                'lesson_title' => $selectedConfig->lesson?->title,
                'module_title' => $selectedConfig->lesson?->module?->title,
                'activity_type' => $selectedConfig->activity_type,
                'max_attempts' => $selectedConfig->max_attempts,
                'passing_score' => $selectedConfig->passing_score,
                'xp_reward' => $selectedConfig->xp_reward,
                'coin_reward' => $selectedConfig->coin_reward,
            ] : null,
            'rows' => $rows,
        ]);
    }

    public function alerts(Request $request)
    {
        $teacher = $request->user();

        $managedCourseIds = Course::query()
            ->when(! $teacher->isAdmin(), fn ($query) => $query->where('instructor_id', $teacher->id))
            ->pluck('id');

        $commentAlerts = Comment::query()
            ->whereNull('parent_id')
            ->where('is_question', true)
            ->whereNull('resolved_at')
            ->where(function ($query) use ($managedCourseIds) {
                $query->whereHasMorph(
                    'commentable',
                    [InteractiveConfig::class],
                    fn ($morphQuery) => $morphQuery->whereIn('course_id', $managedCourseIds)
                )->orWhereHasMorph(
                    'commentable',
                    [LessonVideo::class, LessonReading::class, LessonResource::class],
                    fn ($morphQuery) => $morphQuery->whereHas('lesson.module', fn ($moduleQuery) => $moduleQuery->whereIn('course_id', $managedCourseIds))
                );
            })
            ->with('user:id,name,avatar', 'replies')
            ->latest()
            ->limit(6)
            ->get()
            ->map(function (Comment $comment) {
                return [
                    'id' => $comment->id,
                    'type' => 'question',
                    'message' => $comment->body,
                    'student_name' => $comment->user?->name,
                    'created_at' => $comment->created_at,
                    'reply_count' => $comment->replies->count(),
                ];
            });

        $riskAlerts = Enrollment::query()
            ->whereIn('course_id', $managedCourseIds)
            ->with('user:id,name,avatar,last_active_at', 'course:id,title')
            ->get()
            ->filter(function (Enrollment $enrollment) {
                $daysInactive = optional($enrollment->user?->last_active_at)->diffInDays(now()) ?? 999;
                $failedCount = InteractiveActivityResult::query()
                    ->where('user_id', $enrollment->user_id)
                    ->where('course_id', $enrollment->course_id)
                    ->where('status', 'failed')
                    ->count();

                return $daysInactive >= 5 || $failedCount >= 3;
            })
            ->take(6)
            ->map(function (Enrollment $enrollment) {
                $daysInactive = optional($enrollment->user?->last_active_at)->diffInDays(now()) ?? null;
                $failedCount = InteractiveActivityResult::query()
                    ->where('user_id', $enrollment->user_id)
                    ->where('course_id', $enrollment->course_id)
                    ->where('status', 'failed')
                    ->count();

                return [
                    'type' => 'student_risk',
                    'student_name' => $enrollment->user?->name,
                    'course_title' => $enrollment->course?->title,
                    'days_inactive' => $daysInactive,
                    'failed_activities_count' => $failedCount,
                    'created_at' => $enrollment->user?->last_active_at,
                ];
            })
            ->values();

        return response()->json([
            'alerts' => $commentAlerts->concat($riskAlerts)->sortByDesc('created_at')->values(),
            'summary' => [
                'open_questions' => $commentAlerts->count(),
                'risk_students' => $riskAlerts->count(),
            ],
        ]);
    }

    private function authorizeCourseManager(User $user, Course $course): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($user->isInstructor() && (int) $course->instructor_id === (int) $user->id) {
            return;
        }

        abort(403, 'No tienes permisos para acceder a este curso.');
    }

    private function buildAlertIndex(?Carbon $lastActivity, int $failedActivitiesCount): array
    {
        $daysInactive = $lastActivity ? $lastActivity->diffInDays(now()) : null;

        return [
            'severity' => match (true) {
                $failedActivitiesCount >= 3 || ($daysInactive !== null && $daysInactive >= 7) => 'high',
                $failedActivitiesCount >= 2 || ($daysInactive !== null && $daysInactive >= 5) => 'medium',
                default => 'normal',
            },
            'days_inactive' => $daysInactive,
            'failed_activities_count' => $failedActivitiesCount,
        ];
    }
}
