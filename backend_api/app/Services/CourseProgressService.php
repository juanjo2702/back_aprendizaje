<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\InteractiveActivityResult;
use App\Models\Lesson;
use App\Models\User;
use App\Models\UserLessonProgress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CourseProgressService
{
    public function __construct(
        private readonly UserPresentationService $userPresentationService,
        private readonly CertificateAutomationService $certificateAutomationService,
        private readonly BadgeService $badgeService
    ) {
    }

    public function courseHasInteractiveActivities(Course $course): bool
    {
        return $course->lessons()
            ->whereIn('type', ['interactive', 'game', 'quiz'])
            ->exists();
    }

    public function markLessonCompleted(User $user, Lesson $lesson, int $timeSpentSeconds = 0): UserLessonProgress
    {
        $course = $lesson->module->course;

        return UserLessonProgress::updateOrCreate(
            [
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
            ],
            [
                'course_id' => $course->id,
                'module_id' => $lesson->module_id,
                'started_at' => now(),
                'completed_at' => now(),
                'time_spent_seconds' => max(0, $timeSpentSeconds),
                'is_completed' => true,
            ]
        );
    }

    public function recordInteractiveCompletion(
        User $user,
        Lesson $lesson,
        string $sourceType,
        int $sourceId,
        ?float $score = null,
        ?float $maxScore = null,
        int $xpAwarded = 0,
        array $badgesAwarded = [],
        int $attemptsUsed = 1,
        int $coinAwarded = 0,
        bool $isLocked = false,
        bool $requiresTeacherReset = false
    ): InteractiveActivityResult {
        $course = $lesson->module->course;
        $interactiveConfigId = $lesson->interactiveConfig?->id;

        return InteractiveActivityResult::updateOrCreate(
            [
                'user_id' => $user->id,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ],
            [
                'course_id' => $course->id,
                'module_id' => $lesson->module_id,
                'lesson_id' => $lesson->id,
                'interactive_config_id' => $interactiveConfigId,
                'score' => $score,
                'max_score' => $maxScore,
                'attempts_used' => max(0, $attemptsUsed),
                'xp_awarded' => max(0, $xpAwarded),
                'coin_awarded' => max(0, $coinAwarded),
                'badges_awarded' => $badgesAwarded,
                'status' => $isLocked ? 'failed' : 'completed',
                'is_locked' => $isLocked,
                'requires_teacher_reset' => $requiresTeacherReset,
                'completed_at' => now(),
                'last_attempt_at' => now(),
            ]
        );
    }

    public function hasBlockingLockedActivity(User $user, Course $course, ?int $ignoreLessonId = null): bool
    {
        return InteractiveActivityResult::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('is_locked', true)
            ->where('requires_teacher_reset', true)
            ->when($ignoreLessonId, fn ($query) => $query->where('lesson_id', '!=', $ignoreLessonId))
            ->exists();
    }

    public function getBlockingLockedActivity(User $user, Course $course, ?int $ignoreLessonId = null): ?InteractiveActivityResult
    {
        return InteractiveActivityResult::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('is_locked', true)
            ->where('requires_teacher_reset', true)
            ->when($ignoreLessonId, fn ($query) => $query->where('lesson_id', '!=', $ignoreLessonId))
            ->latest('last_attempt_at')
            ->first();
    }

    public function getProgressSnapshot(User $user, Course $course, bool $persistEnrollment = false): array
    {
        $contentLessonTypes = ['video', 'reading', 'resource', 'text'];

        $totalVideos = $course->lessons()
            ->where('type', 'video')
            ->count();

        $totalContentLessons = $course->lessons()
            ->whereIn('type', $contentLessonTypes)
            ->count();

        $totalActivities = $course->lessons()
            ->whereIn('type', ['interactive', 'game', 'quiz'])
            ->count();

        $totalEligibleUnits = $totalContentLessons + $totalActivities;

        $completedLessons = UserLessonProgress::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('is_completed', true)
            ->whereHas('lesson', fn ($query) => $query->whereIn('type', $contentLessonTypes))
            ->distinct('lesson_id')
            ->count('lesson_id');

        $completedVideos = UserLessonProgress::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('is_completed', true)
            ->whereHas('lesson', fn ($query) => $query->where('type', 'video'))
            ->distinct('lesson_id')
            ->count('lesson_id');

        $videoProgress = $totalVideos > 0 ? round(($completedVideos / $totalVideos) * 100, 2) : 100.0;
        $contentProgress = $totalContentLessons > 0 ? round(($completedLessons / $totalContentLessons) * 100, 2) : 100.0;
        $completedInteractive = InteractiveActivityResult::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', 'completed')
            ->distinct('lesson_id')
            ->count('lesson_id');

        $interactiveProgress = $totalActivities > 0
            ? round(($completedInteractive / $totalActivities) * 100, 2)
            : 100.0;

        $overall = $totalEligibleUnits > 0
            ? round((($completedLessons + $completedInteractive) / $totalEligibleUnits) * 100, 2)
            : 0.0;

        if ($persistEnrollment) {
            Enrollment::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                ],
                [
                    'progress' => $overall,
                ]
            );

            if ($overall >= 100) {
                try {
                    $this->certificateAutomationService->issueIfEligible($user, $course, $overall);
                } catch (\Throwable $exception) {
                    Log::error('No se pudo emitir el certificado al recalcular progreso.', [
                        'user_id' => $user->id,
                        'course_id' => $course->id,
                        'progress_percentage' => $overall,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            $this->badgeService->checkGeneralBadges($user->fresh());
        }

        return [
            'has_interactive_activities' => $totalActivities > 0,
            'videos' => [
                'completed' => $completedVideos,
                'total' => $totalVideos,
                'progress' => $videoProgress,
            ],
            'lessons' => [
                'completed' => $completedLessons,
                'total' => $totalContentLessons,
                'progress' => $contentProgress,
            ],
            'interactive' => [
                'completed' => $completedInteractive,
                'total' => $totalActivities,
                'progress' => $interactiveProgress,
            ],
            'resources' => [
                'total' => $course->lessons()->whereIn('type', ['resource', 'reading', 'text'])->count(),
                'count_towards_progress' => true,
            ],
            'overall_progress' => $overall,
        ];
    }

    public function recalculateEnrollmentProgress(User $user, Course $course): array
    {
        return $this->getProgressSnapshot($user, $course, true);
    }

    public function getCourseLeaderboard(Course $course, int $limit = 10): array
    {
        $rows = DB::table('interactive_activity_results as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.course_id', $course->id)
            ->where('r.status', 'completed')
            ->groupBy('u.id', 'u.name', 'u.avatar')
            ->selectRaw('u.id, u.name, u.avatar, COALESCE(SUM(r.xp_awarded), 0) as total_xp, COUNT(DISTINCT r.lesson_id) as completed_activities')
            ->orderByDesc('total_xp')
            ->orderByDesc('completed_activities')
            ->limit($limit)
            ->get();

        $users = User::query()
            ->whereIn('id', $rows->pluck('id')->all())
            ->with('equippedItems.shopItem')
            ->get()
            ->keyBy('id');

        return $rows->map(function ($row, $index) use ($users) {
            $user = $users->get($row->id);

            return [
                'rank' => $index + 1,
                'user_id' => (int) $row->id,
                'name' => $row->name,
                'avatar' => $row->avatar,
                'equipped_avatar_frame' => $user ? $this->userPresentationService->serializeFrame(
                    $this->userPresentationService->equippedItem($user, 'avatar_frame')
                ) : null,
                'equipped_profile_title' => $user ? $this->userPresentationService->serializeTitle(
                    $this->userPresentationService->equippedItem($user, 'profile_title')
                ) : null,
                'equipped_profile_titles' => $user ? $this->userPresentationService->serializeTitles(
                    $this->userPresentationService->equippedItems($user, 'profile_title', 3)
                ) : [],
                'level_title' => $user?->level_title,
                'total_xp' => (int) $row->total_xp,
                'completed_activities' => (int) $row->completed_activities,
            ];
        })->all();
    }
}
