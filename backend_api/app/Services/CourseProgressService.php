<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\InteractiveActivityResult;
use App\Models\Lesson;
use App\Models\User;
use App\Models\UserLessonProgress;
use Illuminate\Support\Facades\DB;

class CourseProgressService
{
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
        array $badgesAwarded = []
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
                'xp_awarded' => max(0, $xpAwarded),
                'badges_awarded' => $badgesAwarded,
                'status' => 'completed',
                'completed_at' => now(),
            ]
        );
    }

    public function getProgressSnapshot(User $user, Course $course, bool $persistEnrollment = false): array
    {
        $totalLessons = $course->lessons()->count();
        $completedLessons = UserLessonProgress::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('is_completed', true)
            ->distinct('lesson_id')
            ->count('lesson_id');

        $lessonsProgress = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100, 2) : 0.0;
        $hasInteractive = $this->courseHasInteractiveActivities($course);

        $interactiveLessons = 0;
        $completedInteractive = 0;
        $interactiveProgress = 0.0;

        if ($hasInteractive) {
            $interactiveLessons = $course->lessons()
                ->whereIn('type', ['interactive', 'game', 'quiz'])
                ->count();

            $completedInteractive = InteractiveActivityResult::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->where('status', 'completed')
                ->distinct('lesson_id')
                ->count('lesson_id');

            $interactiveProgress = $interactiveLessons > 0
                ? round(($completedInteractive / $interactiveLessons) * 100, 2)
                : 100.0;
        }

        $overall = $hasInteractive
            ? round(($lessonsProgress * 0.7) + ($interactiveProgress * 0.3), 2)
            : $lessonsProgress;

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
        }

        return [
            'has_interactive_activities' => $hasInteractive,
            'lessons' => [
                'completed' => $completedLessons,
                'total' => $totalLessons,
                'progress' => $lessonsProgress,
            ],
            'interactive' => [
                'completed' => $completedInteractive,
                'total' => $interactiveLessons,
                'progress' => $interactiveProgress,
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

        return $rows->map(function ($row, $index) {
            return [
                'rank' => $index + 1,
                'user_id' => (int) $row->id,
                'name' => $row->name,
                'avatar' => $row->avatar,
                'total_xp' => (int) $row->total_xp,
                'completed_activities' => (int) $row->completed_activities,
            ];
        })->all();
    }
}
