<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\InteractiveActivityResult;
use App\Models\InteractiveConfig;
use App\Models\PointsLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ActivityAttemptService
{
    public function __construct(
        private readonly CourseProgressService $courseProgressService
    ) {
    }

    public function submit(User $user, InteractiveConfig $interactiveConfig, int|float $score, int|float $maxScore = 100, array $payload = []): array
    {
        $lesson = $interactiveConfig->lesson()->with('module.course')->firstOrFail();
        $course = $lesson->module->course;
        $isEnrolled = $course->enrollments()->where('user_id', $user->id)->exists();

        if (! $isEnrolled) {
            return [
                'ok' => false,
                'status_code' => 403,
                'message' => 'Debes estar inscrito para enviar intentos en esta actividad.',
            ];
        }

        $existingResult = InteractiveActivityResult::query()
            ->where('user_id', $user->id)
            ->where('interactive_config_id', $interactiveConfig->id)
            ->where('source_type', 'interactive_renderer')
            ->where('source_id', $interactiveConfig->id)
            ->first();

        if ($existingResult?->status === 'completed' && ! $existingResult->requires_teacher_reset) {
            return [
                'ok' => false,
                'status_code' => 409,
                'message' => 'La actividad ya fue aprobada. Si necesitas reabrirla, debe resetearla un docente.',
            ];
        }

        if ($existingResult?->is_locked && $existingResult->requires_teacher_reset) {
            return [
                'ok' => false,
                'status_code' => 423,
                'message' => 'La actividad quedó bloqueada por agotar intentos. Debe intervenir un docente.',
            ];
        }

        $attemptNumber = ActivityLog::query()
            ->where('user_id', $user->id)
            ->where('interactive_config_id', $interactiveConfig->id)
            ->count() + 1;

        $maxAttempts = max(1, (int) ($interactiveConfig->max_attempts ?: 3));
        if ($attemptNumber > $maxAttempts) {
            return [
                'ok' => false,
                'status_code' => 423,
                'message' => 'Ya no tienes intentos disponibles para esta actividad.',
            ];
        }

        $normalizedScore = (int) round(
            min(100, max(0, (((float) $score) / max(1, (float) $maxScore)) * 100))
        );
        $passed = $normalizedScore >= (int) $interactiveConfig->passing_score;
        $multiplier = $interactiveConfig->rewardMultiplierForAttempt($attemptNumber);
        $xpAwarded = $passed ? (int) round($interactiveConfig->xp_reward * $multiplier) : 0;
        $coinAwarded = $passed ? (int) round($interactiveConfig->coin_reward * $multiplier) : 0;
        $locked = ! $passed && $attemptNumber >= $maxAttempts;
        $logStatus = $passed ? 'passed' : ($locked ? 'locked' : 'failed');

        $result = DB::transaction(function () use (
            $user,
            $interactiveConfig,
            $lesson,
            $course,
            $attemptNumber,
            $normalizedScore,
            $xpAwarded,
            $coinAwarded,
            $multiplier,
            $logStatus,
            $passed,
            $locked,
            $payload
        ) {
            ActivityLog::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'module_id' => $lesson->module_id,
                'lesson_id' => $lesson->id,
                'interactive_config_id' => $interactiveConfig->id,
                'attempt_number' => $attemptNumber,
                'score' => $normalizedScore,
                'passing_score' => (int) $interactiveConfig->passing_score,
                'xp_awarded' => $xpAwarded,
                'coin_awarded' => $coinAwarded,
                'reward_multiplier' => $multiplier,
                'status' => $logStatus,
                'payload' => $payload ?: null,
                'attempted_at' => now(),
            ]);

            $aggregate = InteractiveActivityResult::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'source_type' => 'interactive_renderer',
                    'source_id' => $interactiveConfig->id,
                ],
                [
                    'course_id' => $course->id,
                    'module_id' => $lesson->module_id,
                    'lesson_id' => $lesson->id,
                    'interactive_config_id' => $interactiveConfig->id,
                    'score' => $normalizedScore,
                    'max_score' => 100,
                    'attempts_used' => $attemptNumber,
                    'xp_awarded' => $xpAwarded,
                    'coin_awarded' => $coinAwarded,
                    'badges_awarded' => [],
                    'status' => $passed ? 'completed' : 'failed',
                    'is_locked' => $locked,
                    'requires_teacher_reset' => $locked,
                    'completed_at' => $passed ? now() : null,
                    'last_attempt_at' => now(),
                ]
            );

            if ($passed) {
                $user->increment('total_points', $xpAwarded);
                $user->increment('total_coins', $coinAwarded);

                PointsLog::create([
                    'user_id' => $user->id,
                    'points' => $xpAwarded,
                    'source' => 'interactive_attempt',
                    'source_id' => $lesson->id,
                    'description' => 'XP por aprobar actividad interactiva: '.$lesson->title,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->courseProgressService->markLessonCompleted($user, $lesson, (int) ($payload['time_spent_seconds'] ?? 0));
            }

            return $aggregate;
        });

        $snapshot = $this->courseProgressService->recalculateEnrollmentProgress($user, $course);

        return [
            'ok' => true,
            'status_code' => 200,
            'message' => $passed
                ? "Actividad aprobada en el intento {$attemptNumber}."
                : ($locked ? 'Actividad fallida y bloqueada hasta intervención docente.' : 'Intento registrado. Aún no alcanzas la nota mínima.'),
            'xp_awarded' => $xpAwarded,
            'coin_awarded' => $coinAwarded,
            'attempt' => [
                'attempt_number' => $attemptNumber,
                'max_attempts' => $maxAttempts,
                'score' => $normalizedScore,
                'passing_score' => (int) $interactiveConfig->passing_score,
                'passed' => $passed,
                'locked' => $locked,
                'reward_multiplier' => $multiplier,
                'xp_awarded' => $xpAwarded,
                'coin_awarded' => $coinAwarded,
            ],
            'activity_result' => $result,
            'progress' => $snapshot,
        ];
    }

    public function resetForStudent(InteractiveConfig $interactiveConfig, User $student): InteractiveActivityResult
    {
        $lesson = $interactiveConfig->lesson()->with('module.course')->firstOrFail();

        return InteractiveActivityResult::updateOrCreate(
            [
                'user_id' => $student->id,
                'source_type' => 'interactive_renderer',
                'source_id' => $interactiveConfig->id,
            ],
            [
                'course_id' => $lesson->module->course_id,
                'module_id' => $lesson->module_id,
                'lesson_id' => $lesson->id,
                'interactive_config_id' => $interactiveConfig->id,
                'score' => null,
                'max_score' => 100,
                'attempts_used' => 0,
                'xp_awarded' => 0,
                'coin_awarded' => 0,
                'badges_awarded' => [],
                'status' => 'started',
                'is_locked' => false,
                'requires_teacher_reset' => false,
                'completed_at' => null,
                'last_attempt_at' => null,
            ]
        );
    }
}
