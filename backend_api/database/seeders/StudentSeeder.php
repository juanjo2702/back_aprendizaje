<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Badge;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Comment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GameSession;
use App\Models\InteractiveActivityResult;
use App\Models\Lesson;
use App\Models\Payment;
use App\Models\PointsLog;
use App\Models\Purchase;
use App\Models\ShopItem;
use App\Models\User;
use App\Models\UserLessonProgress;
use App\Models\UserQuizAttempt;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $students = collect($this->studentProfiles())->map(function (array $profile) {
            return User::create([
                'name' => $profile['name'],
                'email' => $profile['email'],
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'avatar' => $profile['avatar'],
                'role' => 'student',
                'bio' => $profile['bio'],
                'total_points' => $profile['total_points'],
                'total_coins' => $this->startingCoinsForProfile($profile['profile']),
                'current_streak' => $profile['current_streak'],
                'last_active_at' => $profile['last_active_at'],
            ]);
        })->keyBy('email');

        $courses = Course::with([
            'modules' => fn ($query) => $query->orderBy('sort_order'),
            'modules.lessons' => fn ($query) => $query->orderBy('sort_order'),
            'modules.lessons.interactiveConfig',
            'modules.lessons.quiz.questions',
            'modules.lessons.contentable',
        ])->get()->keyBy('slug');

        $badges = Badge::all()->keyBy('slug');
        $defaultTemplate = CertificateTemplate::query()->where('is_default', true)->first()
            ?? CertificateTemplate::query()->first();

        foreach ($this->studentProfiles() as $profile) {
            $student = $students[$profile['email']];

            DB::transaction(function () use ($student, $profile, $courses, $badges, $defaultTemplate) {
                foreach ($profile['enrollments'] as $index => $enrollmentPlan) {
                    $course = $courses[$enrollmentPlan['course_slug']];
                    $enrolledAt = Carbon::parse($enrollmentPlan['enrolled_at']);
                    $payment = $this->createPayment($student, $course, $enrolledAt, $enrollmentPlan['payment_status']);

                    $enrollment = Enrollment::create([
                        'user_id' => $student->id,
                        'course_id' => $course->id,
                        'progress' => $enrollmentPlan['progress'],
                        'enrolled_at' => $enrolledAt,
                    ]);

                    $this->seedCourseProgress(
                        $student,
                        $course,
                        $enrollmentPlan['progress'],
                        $enrolledAt,
                        $profile['profile'],
                        $profile['skill_bias'],
                        $index
                    );

                    if ($enrollment->progress >= 100) {
                        $issuedAt = $enrolledAt->copy()->addDays(fake()->numberBetween(18, 36));
                        $this->createCertificate($student, $course, $defaultTemplate, $issuedAt);
                        $this->logPoints(
                            $student,
                            250,
                            'course_completion',
                            $course->id,
                            'Curso completado con certificado: '.$course->title,
                            $issuedAt
                        );
                    }

                    if ($payment->status === 'completed') {
                        $this->logPoints(
                            $student,
                            25,
                            'enrollment',
                            $course->id,
                            'Inscripción procesada para '.$course->title,
                            $enrolledAt
                        );
                    }
                }

                $this->attachBadges($student, $profile['badge_slugs'], $badges);

                if (! empty($profile['pending_checkout_course_slug'])) {
                    $course = $courses[$profile['pending_checkout_course_slug']];
                    $this->createPayment(
                        $student,
                        $course,
                        now()->subDays(fake()->numberBetween(1, 3)),
                        'pending'
                    );
                }

                $this->seedRewardPurchases($student, $profile);
                $this->seedCommentsAndThreads($student, $profile, $courses);
            });
        }
    }

    private function createPayment(User $student, Course $course, Carbon $createdAt, string $status): Payment
    {
        return Payment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'amount' => $course->price,
            'status' => $status,
            'qr_data' => json_encode([
                'provider' => 'qr-local',
                'reference' => 'QR-'.Str::upper(Str::random(10)),
                'bank' => 'Banco Demo Bolivia',
                'expires_at' => $createdAt->copy()->addMinutes(25)->toIso8601String(),
            ], JSON_UNESCAPED_UNICODE),
            'transaction_id' => 'TX-'.Str::upper(Str::random(12)),
            'created_at' => $createdAt,
            'updated_at' => $createdAt->copy()->addMinutes(fake()->numberBetween(5, 120)),
        ]);
    }

    private function seedCourseProgress(
        User $student,
        Course $course,
        float $progressPercent,
        Carbon $enrolledAt,
        string $profileType,
        string $skillBias,
        int $courseOffset
    ): void {
        $lessonEntries = $course->modules
            ->sortBy('sort_order')
            ->flatMap(function ($module) {
                return $module->lessons->sortBy('sort_order')->map(function ($lesson) use ($module) {
                    return [
                        'module' => $module,
                        'lesson' => $lesson,
                    ];
                });
            })
            ->values();

        $totalLessons = $lessonEntries->count();
        $completedLessons = (int) floor($totalLessons * ($progressPercent / 100));
        $hasPartialLesson = $progressPercent > 0 && $progressPercent < 100 && $completedLessons < $totalLessons;
        $cursor = $enrolledAt->copy()->addHours(4 + ($courseOffset * 3));

        foreach ($lessonEntries as $index => $entry) {
            $module = $entry['module'];
            /** @var Lesson $lesson */
            $lesson = $entry['lesson'];

            if ($index < $completedLessons) {
                $startedAt = $cursor->copy();
                $spentSeconds = $this->resolveSpentSeconds($lesson, true, $profileType);
                $completedAt = $startedAt->copy()->addSeconds($spentSeconds);

                UserLessonProgress::create([
                    'user_id' => $student->id,
                    'course_id' => $course->id,
                    'module_id' => $module->id,
                    'lesson_id' => $lesson->id,
                    'started_at' => $startedAt,
                    'completed_at' => $completedAt,
                    'time_spent_seconds' => $spentSeconds,
                    'is_completed' => true,
                ]);

                if ($lesson->type === 'interactive') {
                    $this->recordInteractiveCompletion(
                        $student,
                        $course,
                        $module->id,
                        $lesson,
                        $completedAt,
                        $profileType,
                        $skillBias
                    );
                }

                $cursor = $completedAt->copy()->addHours(fake()->numberBetween(8, 36));

                continue;
            }

            if ($hasPartialLesson && $index === $completedLessons) {
                $startedAt = $cursor->copy();
                $spentSeconds = $this->resolveSpentSeconds($lesson, false, $profileType);

                UserLessonProgress::create([
                    'user_id' => $student->id,
                    'course_id' => $course->id,
                    'module_id' => $module->id,
                    'lesson_id' => $lesson->id,
                    'started_at' => $startedAt,
                    'completed_at' => null,
                    'time_spent_seconds' => $spentSeconds,
                    'is_completed' => false,
                ]);

                if ($lesson->type === 'interactive' && $profileType === 'in_progress') {
                    $this->recordInteractiveLowScore(
                        $student,
                        $course,
                        $module->id,
                        $lesson,
                        $startedAt->copy()->addMinutes(fake()->numberBetween(10, 25)),
                        $skillBias
                    );
                }
            }
        }

        if ($profileType === 'in_progress') {
            $interactiveEntry = $lessonEntries->first(fn (array $entry) => $entry['lesson']->type === 'interactive');

            if ($interactiveEntry) {
                $this->recordInteractiveLowScore(
                    $student,
                    $course,
                    $interactiveEntry['module']->id,
                    $interactiveEntry['lesson'],
                    $cursor->copy()->subHours(6),
                    $skillBias
                );
            }
        }
    }

    private function recordInteractiveCompletion(
        User $student,
        Course $course,
        int $moduleId,
        Lesson $lesson,
        Carbon $completedAt,
        string $profileType,
        string $skillBias
    ): void {
        $score = match ($profileType) {
            'overachiever' => fake()->numberBetween(88, 100),
            'in_progress' => fake()->numberBetween(62, 84),
            default => fake()->numberBetween(55, 75),
        };

        if ($lesson->quiz) {
            $totalQuestions = max($lesson->quiz->questions->count(), 3);
            $correctAnswers = max(1, (int) round(($score / 100) * $totalQuestions));
            $startedAt = $completedAt->copy()->subMinutes(fake()->numberBetween(8, 18));
            $attemptNumber = 1;

            $attempt = UserQuizAttempt::create([
                'user_id' => $student->id,
                'quiz_id' => $lesson->quiz->id,
                'course_id' => $course->id,
                'module_id' => $moduleId,
                'score' => $score,
                'total_questions' => $totalQuestions,
                'correct_answers' => min($correctAnswers, $totalQuestions),
                'time_spent' => $completedAt->diffInSeconds($startedAt),
                'status' => 'completed',
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'percentage' => $score,
                'created_at' => $startedAt,
                'updated_at' => $completedAt,
            ]);

            $xpAwarded = (int) round($score * 0.7);
            $coinAwarded = (int) round($xpAwarded / 4);

            ActivityLog::create([
                'user_id' => $student->id,
                'course_id' => $course->id,
                'module_id' => $moduleId,
                'lesson_id' => $lesson->id,
                'interactive_config_id' => $lesson->interactiveConfig?->id,
                'attempt_number' => $attemptNumber,
                'score' => $score,
                'passing_score' => 70,
                'xp_awarded' => $xpAwarded,
                'coin_awarded' => $coinAwarded,
                'reward_multiplier' => 1,
                'status' => 'passed',
                'payload' => ['seeded' => true, 'source' => 'quiz_attempt'],
                'attempted_at' => $completedAt,
                'created_at' => $completedAt,
                'updated_at' => $completedAt,
            ]);

            InteractiveActivityResult::create([
                'user_id' => $student->id,
                'course_id' => $course->id,
                'module_id' => $moduleId,
                'lesson_id' => $lesson->id,
                'interactive_config_id' => $lesson->interactiveConfig?->id,
                'source_type' => 'quiz_attempt',
                'source_id' => $attempt->id,
                'score' => $score,
                'max_score' => 100,
                'attempts_used' => $attemptNumber,
                'xp_awarded' => $xpAwarded,
                'coin_awarded' => $coinAwarded,
                'badges_awarded' => $score >= 95 ? ['perfect_quiz'] : [],
                'status' => 'completed',
                'is_locked' => false,
                'requires_teacher_reset' => false,
                'completed_at' => $completedAt,
                'last_attempt_at' => $completedAt,
                'created_at' => $completedAt,
                'updated_at' => $completedAt,
            ]);
        } else {
            $startedAt = $completedAt->copy()->subMinutes(fake()->numberBetween(6, 14));
            $attemptNumber = 1;

            $session = GameSession::create([
                'user_id' => $student->id,
                'game_config_id' => $lesson->game_config_id,
                'course_id' => $course->id,
                'module_id' => $moduleId,
                'lesson_id' => $lesson->id,
                'score' => $score,
                'time_spent' => $completedAt->diffInSeconds($startedAt),
                'attempt' => 1,
                'status' => 'completed',
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'game_data' => [
                    'activity_type' => $lesson->interactiveConfig?->activity_type,
                    'correct_sequence' => fake()->boolean(80),
                    'skill_bias' => $skillBias,
                ],
                'details' => [
                    'skill_area' => $skillBias,
                    'module_title' => $lesson->module->title,
                    'course_title' => $course->title,
                ],
                'created_at' => $startedAt,
                'updated_at' => $completedAt,
            ]);

            $xpAwarded = (int) round($score * 0.6);
            $coinAwarded = (int) round($xpAwarded / 4);

            ActivityLog::create([
                'user_id' => $student->id,
                'course_id' => $course->id,
                'module_id' => $moduleId,
                'lesson_id' => $lesson->id,
                'interactive_config_id' => $lesson->interactiveConfig?->id,
                'attempt_number' => $attemptNumber,
                'score' => $score,
                'passing_score' => 70,
                'xp_awarded' => $xpAwarded,
                'coin_awarded' => $coinAwarded,
                'reward_multiplier' => 1,
                'status' => 'passed',
                'payload' => ['seeded' => true, 'source' => 'game_session'],
                'attempted_at' => $completedAt,
                'created_at' => $completedAt,
                'updated_at' => $completedAt,
            ]);

            InteractiveActivityResult::create([
                'user_id' => $student->id,
                'course_id' => $course->id,
                'module_id' => $moduleId,
                'lesson_id' => $lesson->id,
                'interactive_config_id' => $lesson->interactiveConfig?->id,
                'source_type' => 'game_session',
                'source_id' => $session->id,
                'score' => $score,
                'max_score' => 100,
                'attempts_used' => $attemptNumber,
                'xp_awarded' => $xpAwarded,
                'coin_awarded' => $coinAwarded,
                'badges_awarded' => $score >= 96 ? ['perfect_sequence'] : [],
                'status' => 'completed',
                'is_locked' => false,
                'requires_teacher_reset' => false,
                'completed_at' => $completedAt,
                'last_attempt_at' => $completedAt,
                'created_at' => $completedAt,
                'updated_at' => $completedAt,
            ]);
        }

        $this->logPoints(
            $student,
            (int) round($score * 0.45),
            'interactive_activity',
            $lesson->id,
            'Actividad interactiva completada en '.$course->title,
            $completedAt
        );
    }

    private function recordInteractiveLowScore(
        User $student,
        Course $course,
        int $moduleId,
        Lesson $lesson,
        Carbon $attemptedAt,
        string $skillBias
    ): void {
        $score = fake()->numberBetween(12, 48);
        $startedAt = $attemptedAt->copy()->subMinutes(fake()->numberBetween(4, 12));
        $attemptNumber = fake()->numberBetween(1, 2);

        $session = GameSession::create([
            'user_id' => $student->id,
            'game_config_id' => $lesson->game_config_id,
            'course_id' => $course->id,
            'module_id' => $moduleId,
            'lesson_id' => $lesson->id,
            'score' => $score,
            'time_spent' => $attemptedAt->diffInSeconds($startedAt),
            'attempt' => $attemptNumber,
            'status' => 'completed',
            'started_at' => $startedAt,
            'completed_at' => $attemptedAt,
            'game_data' => [
                'activity_type' => $lesson->interactiveConfig?->activity_type,
                'skill_bias' => $skillBias,
                'error_pattern' => fake()->randomElement(['precision', 'sequence', 'time_pressure']),
            ],
            'details' => [
                'skill_area' => $skillBias,
                'course_title' => $course->title,
                'result' => 'retry_required',
            ],
            'created_at' => $startedAt,
            'updated_at' => $attemptedAt,
        ]);

        ActivityLog::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'module_id' => $moduleId,
            'lesson_id' => $lesson->id,
            'interactive_config_id' => $lesson->interactiveConfig?->id,
            'attempt_number' => $attemptNumber,
            'score' => $score,
            'passing_score' => 70,
            'xp_awarded' => 5,
            'coin_awarded' => 0,
            'reward_multiplier' => $attemptNumber === 2 ? 0.7 : 1,
            'status' => 'failed',
            'payload' => ['seeded' => true, 'source' => 'game_session', 'result' => 'retry_required'],
            'attempted_at' => $attemptedAt,
            'created_at' => $attemptedAt,
            'updated_at' => $attemptedAt,
        ]);

        InteractiveActivityResult::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'module_id' => $moduleId,
            'lesson_id' => $lesson->id,
            'interactive_config_id' => $lesson->interactiveConfig?->id,
            'source_type' => 'game_session',
            'source_id' => $session->id,
            'score' => $score,
            'max_score' => 100,
            'attempts_used' => $attemptNumber,
            'xp_awarded' => 5,
            'coin_awarded' => 0,
            'badges_awarded' => [],
            'status' => 'failed',
            'is_locked' => false,
            'requires_teacher_reset' => false,
            'completed_at' => $attemptedAt,
            'last_attempt_at' => $attemptedAt,
            'created_at' => $attemptedAt,
            'updated_at' => $attemptedAt,
        ]);

        $this->logPoints(
            $student,
            5,
            'interactive_retry',
            $lesson->id,
            'Intento con baja puntuación registrado para análisis de mejora en '.$course->title,
            $attemptedAt
        );
    }

    private function createCertificate(User $student, Course $course, ?CertificateTemplate $template, Carbon $issuedAt): void
    {
        Certificate::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'template_id' => $template?->id,
            'certificate_code' => 'CERT-'.Str::upper(Str::random(10)),
            'student_name' => $student->name,
            'course_name' => $course->title,
            'final_score' => fake()->numberBetween($course->certificate_min_score, 100),
            'issued_at' => $issuedAt,
            'expiry_date' => null,
            'download_url' => 'https://plataforma.test/certificados/'.Str::uuid(),
            'metadata' => [
                'validation_url' => 'https://plataforma.test/certificados/validar/'.Str::uuid(),
                'awarded_by' => 'LMS Creator',
            ],
        ]);
    }

    private function attachBadges(User $student, array $badgeSlugs, Collection $badges): void
    {
        foreach ($badgeSlugs as $offset => $slug) {
            $badge = $badges->get($slug);

            if (! $badge) {
                continue;
            }

            $student->badges()->attach($badge->id, [
                'earned_at' => now()->subDays(15 - $offset),
                'created_at' => now()->subDays(15 - $offset),
                'updated_at' => now()->subDays(15 - $offset),
            ]);
        }
    }

    private function logPoints(
        User $student,
        int $points,
        string $source,
        ?int $sourceId,
        string $description,
        Carbon $createdAt
    ): void {
        PointsLog::create([
            'user_id' => $student->id,
            'points' => $points,
            'source' => $source,
            'source_id' => $sourceId,
            'description' => $description,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function seedRewardPurchases(User $student, array $profile): void
    {
        $catalog = ShopItem::query()->where('is_active', true)->get()->keyBy('slug');

        $purchasePlans = match ($profile['profile']) {
            'overachiever' => [
                'marco-avatar-aurora',
                'titulo-veterano',
                'cupon-10-marketplace',
            ],
            'in_progress' => [
                'cupon-10-marketplace',
            ],
            default => [],
        };

        if ($student->email === 'lucia.rios@plataforma.com') {
            $purchasePlans[] = 'titulo-maestro';
        }

        foreach ($purchasePlans as $offset => $slug) {
            $item = $catalog->get($slug);

            if (! $item) {
                continue;
            }

            Purchase::create([
                'user_id' => $student->id,
                'shop_item_id' => $item->id,
                'cost_coins' => $item->cost_coins,
                'status' => 'completed',
                'metadata' => match ($item->type) {
                    'discount_coupon' => [
                        'coupon_code' => strtoupper(($item->metadata['code_prefix'] ?? 'SAVE').'-'.Str::random(6)),
                        'discount_percent' => $item->metadata['discount_percent'] ?? 10,
                    ],
                    'profile_title' => ['title' => $item->metadata['title'] ?? $item->name],
                    'avatar_frame' => ['frame_style' => $item->metadata['frame_style'] ?? 'aurora-neon'],
                    default => $item->metadata,
                },
                'purchased_at' => now()->subDays(8 - $offset),
                'created_at' => now()->subDays(8 - $offset),
                'updated_at' => now()->subDays(8 - $offset),
            ]);

            $student->decrement('total_coins', $item->cost_coins);
        }
    }

    private function startingCoinsForProfile(string $profile): int
    {
        return match ($profile) {
            'overachiever' => 1400,
            'in_progress' => 650,
            default => 180,
        };
    }

    private function seedCommentsAndThreads(User $student, array $profile, Collection $courses): void
    {
        if (! in_array($profile['profile'], ['in_progress', 'newbie'], true)) {
            return;
        }

        $courseSlug = $profile['enrollments'][0]['course_slug'] ?? null;
        $course = $courseSlug ? $courses->get($courseSlug) : null;

        if (! $course) {
            return;
        }

        $videoLesson = $course->modules->flatMap->lessons->first(fn ($lesson) => $lesson->type === 'video' && $lesson->contentable);
        $resourceLesson = $course->modules->flatMap->lessons->first(fn ($lesson) => $lesson->type === 'resource' && $lesson->contentable);
        $interactiveLesson = $course->modules->flatMap->lessons->first(fn ($lesson) => $lesson->type === 'interactive' && $lesson->interactiveConfig);
        $teacher = User::find($course->instructor_id);

        $baseDate = now()->subDays($profile['profile'] === 'newbie' ? 1 : 4);

        if ($videoLesson) {
            Comment::create([
                'user_id' => $student->id,
                'commentable_type' => $videoLesson->contentable->getMorphClass(),
                'commentable_id' => $videoLesson->contentable->getKey(),
                'body' => '¿Podrías profundizar el paso donde cambia el flujo principal? Me perdí justo en esa transición.',
                'is_question' => true,
                'created_at' => $baseDate,
                'updated_at' => $baseDate,
            ]);
        }

        if ($resourceLesson) {
            $question = Comment::create([
                'user_id' => $student->id,
                'commentable_type' => $resourceLesson->contentable->getMorphClass(),
                'commentable_id' => $resourceLesson->contentable->getKey(),
                'body' => 'En el PDF me quedó una duda sobre el criterio de validación del checklist.',
                'is_question' => true,
                'resolved_at' => $teacher ? $baseDate->copy()->addHours(8) : null,
                'created_at' => $baseDate->copy()->addHours(2),
                'updated_at' => $baseDate->copy()->addHours(8),
            ]);

            if ($teacher) {
                Comment::create([
                    'user_id' => $teacher->id,
                    'parent_id' => $question->id,
                    'commentable_type' => $resourceLesson->contentable->getMorphClass(),
                    'commentable_id' => $resourceLesson->contentable->getKey(),
                    'body' => 'Sí, revisa la segunda tabla del documento: ahí está la lógica completa y el ejemplo del caso.',
                    'is_question' => false,
                    'created_at' => $baseDate->copy()->addHours(8),
                    'updated_at' => $baseDate->copy()->addHours(8),
                ]);
            }
        }

        if ($interactiveLesson && $profile['profile'] === 'in_progress') {
            Comment::create([
                'user_id' => $student->id,
                'commentable_type' => $interactiveLesson->interactiveConfig->getMorphClass(),
                'commentable_id' => $interactiveLesson->interactiveConfig->getKey(),
                'body' => 'Fallé tres veces esta actividad. ¿Qué pista me recomiendas revisar antes de volver a intentarlo?',
                'is_question' => true,
                'created_at' => $baseDate->copy()->addHours(14),
                'updated_at' => $baseDate->copy()->addHours(14),
            ]);
        }
    }

    private function resolveSpentSeconds(Lesson $lesson, bool $completed, string $profileType): int
    {
        $base = max($lesson->duration ?? 600, 180);

        if (! $completed) {
            return (int) round($base * fake()->randomFloat(2, 0.18, 0.55));
        }

        return match ($profileType) {
            'overachiever' => (int) round($base * fake()->randomFloat(2, 0.88, 1.08)),
            'in_progress' => (int) round($base * fake()->randomFloat(2, 0.72, 1.12)),
            default => (int) round($base * fake()->randomFloat(2, 0.4, 0.7)),
        };
    }

    private function studentProfiles(): array
    {
        return [
            [
                'profile' => 'overachiever',
                'name' => 'Lucía Ríos',
                'email' => 'lucia.rios@plataforma.com',
                'avatar' => 'https://i.pravatar.cc/300?img=41',
                'bio' => 'Aprende de forma intensiva y documenta cada mejora de proceso.',
                'total_points' => 4380,
                'current_streak' => 24,
                'last_active_at' => now()->subHours(2),
                'skill_bias' => 'Lógica',
                'badge_slugs' => ['primer-curso-completado', 'racha-7-dias', 'puntos-legendarios', 'maestro-de-juegos'],
                'enrollments' => [
                    ['course_slug' => 'arquitectura-full-stack-con-laravel-y-quasar', 'progress' => 100, 'enrolled_at' => now()->subDays(54)->toDateTimeString(), 'payment_status' => 'completed'],
                    ['course_slug' => 'gestion-de-sistemas-y-observabilidad-operativa', 'progress' => 100, 'enrolled_at' => now()->subDays(32)->toDateTimeString(), 'payment_status' => 'completed'],
                    ['course_slug' => 'sistemas-de-informacion-para-nutricion-clinica', 'progress' => 68, 'enrolled_at' => now()->subDays(10)->toDateTimeString(), 'payment_status' => 'completed'],
                ],
            ],
            [
                'profile' => 'overachiever',
                'name' => 'Mateo Salazar',
                'email' => 'mateo.salazar@plataforma.com',
                'avatar' => 'https://i.pravatar.cc/300?img=22',
                'bio' => 'Le gusta cerrar cursos completos y comparar tableros de avance.',
                'total_points' => 4015,
                'current_streak' => 19,
                'last_active_at' => now()->subHours(6),
                'skill_bias' => 'Operaciones',
                'badge_slugs' => ['primer-curso-completado', 'racha-7-dias', 'puntos-legendarios'],
                'enrollments' => [
                    ['course_slug' => 'informatica-medica-para-flujos-clinicos-digitales', 'progress' => 100, 'enrolled_at' => now()->subDays(49)->toDateTimeString(), 'payment_status' => 'completed'],
                    ['course_slug' => 'gestion-de-sistemas-y-observabilidad-operativa', 'progress' => 100, 'enrolled_at' => now()->subDays(28)->toDateTimeString(), 'payment_status' => 'completed'],
                    ['course_slug' => 'arquitectura-full-stack-con-laravel-y-quasar', 'progress' => 52, 'enrolled_at' => now()->subDays(7)->toDateTimeString(), 'payment_status' => 'completed'],
                ],
            ],
            [
                'profile' => 'overachiever',
                'name' => 'Valeria Ortiz',
                'email' => 'valeria.ortiz@plataforma.com',
                'avatar' => 'https://i.pravatar.cc/300?img=50',
                'bio' => 'Combina diseño de dashboards con seguimiento clínico de alto detalle.',
                'total_points' => 3890,
                'current_streak' => 17,
                'last_active_at' => now()->subHours(9),
                'skill_bias' => 'Diseño',
                'badge_slugs' => ['primer-curso-completado', 'racha-7-dias', 'puntos-legendarios'],
                'enrollments' => [
                    ['course_slug' => 'sistemas-de-informacion-para-nutricion-clinica', 'progress' => 100, 'enrolled_at' => now()->subDays(45)->toDateTimeString(), 'payment_status' => 'completed'],
                    ['course_slug' => 'informatica-medica-para-flujos-clinicos-digitales', 'progress' => 100, 'enrolled_at' => now()->subDays(23)->toDateTimeString(), 'payment_status' => 'completed'],
                    ['course_slug' => 'gestion-de-sistemas-y-observabilidad-operativa', 'progress' => 63, 'enrolled_at' => now()->subDays(6)->toDateTimeString(), 'payment_status' => 'completed'],
                ],
            ],
            [
                'profile' => 'in_progress',
                'name' => 'Juan Pérez',
                'email' => 'estudiante@plataforma.com',
                'avatar' => 'https://i.pravatar.cc/300?img=12',
                'bio' => 'Usuario demo del frontend con cursos y actividades a medio camino.',
                'total_points' => 1280,
                'current_streak' => 0,
                'last_active_at' => now()->subHours(4),
                'skill_bias' => 'Lógica',
                'badge_slugs' => [],
                'enrollments' => [
                    ['course_slug' => 'arquitectura-full-stack-con-laravel-y-quasar', 'progress' => 70, 'enrolled_at' => now()->subDays(18)->toDateTimeString(), 'payment_status' => 'completed'],
                    ['course_slug' => 'sistemas-de-informacion-para-nutricion-clinica', 'progress' => 45, 'enrolled_at' => now()->subDays(8)->toDateTimeString(), 'payment_status' => 'completed'],
                ],
            ],
            [
                'profile' => 'in_progress',
                'name' => 'Camila Vargas',
                'email' => 'camila.vargas@plataforma.com',
                'avatar' => 'https://i.pravatar.cc/300?img=27',
                'bio' => 'Estudia por bloques cortos y suele reintentar actividades interactivas.',
                'total_points' => 1110,
                'current_streak' => 2,
                'last_active_at' => now()->subDay(),
                'skill_bias' => 'Diseño',
                'badge_slugs' => [],
                'enrollments' => [
                    ['course_slug' => 'informatica-medica-para-flujos-clinicos-digitales', 'progress' => 70, 'enrolled_at' => now()->subDays(20)->toDateTimeString(), 'payment_status' => 'completed'],
                    ['course_slug' => 'gestion-de-sistemas-y-observabilidad-operativa', 'progress' => 45, 'enrolled_at' => now()->subDays(11)->toDateTimeString(), 'payment_status' => 'completed'],
                ],
            ],
            [
                'profile' => 'in_progress',
                'name' => 'Diego Torres',
                'email' => 'diego.torres@plataforma.com',
                'avatar' => 'https://i.pravatar.cc/300?img=57',
                'bio' => 'Alterna aprendizaje técnico con seguimiento de operaciones.',
                'total_points' => 980,
                'current_streak' => 1,
                'last_active_at' => now()->subDays(2),
                'skill_bias' => 'Operaciones',
                'badge_slugs' => [],
                'enrollments' => [
                    ['course_slug' => 'gestion-de-sistemas-y-observabilidad-operativa', 'progress' => 70, 'enrolled_at' => now()->subDays(16)->toDateTimeString(), 'payment_status' => 'completed'],
                    ['course_slug' => 'arquitectura-full-stack-con-laravel-y-quasar', 'progress' => 45, 'enrolled_at' => now()->subDays(9)->toDateTimeString(), 'payment_status' => 'completed'],
                ],
            ],
            [
                'profile' => 'in_progress',
                'name' => 'Sofía Méndez',
                'email' => 'sofia.mendez@plataforma.com',
                'avatar' => 'https://i.pravatar.cc/300?img=37',
                'bio' => 'Le interesan los módulos clínicos y de negocio, pero avanza a ritmo variable.',
                'total_points' => 1365,
                'current_streak' => 3,
                'last_active_at' => now()->subHours(20),
                'skill_bias' => 'Negocios',
                'badge_slugs' => [],
                'enrollments' => [
                    ['course_slug' => 'sistemas-de-informacion-para-nutricion-clinica', 'progress' => 70, 'enrolled_at' => now()->subDays(15)->toDateTimeString(), 'payment_status' => 'completed'],
                    ['course_slug' => 'informatica-medica-para-flujos-clinicos-digitales', 'progress' => 45, 'enrolled_at' => now()->subDays(6)->toDateTimeString(), 'payment_status' => 'completed'],
                ],
            ],
            [
                'profile' => 'newbie',
                'name' => 'Daniela Choque',
                'email' => 'daniela.choque@plataforma.com',
                'avatar' => 'https://i.pravatar.cc/300?img=61',
                'bio' => 'Acaba de entrar a la plataforma y está explorando sus primeros contenidos.',
                'total_points' => 95,
                'current_streak' => 1,
                'last_active_at' => now()->subHours(7),
                'skill_bias' => 'Lógica',
                'badge_slugs' => [],
                'pending_checkout_course_slug' => 'informatica-medica-para-flujos-clinicos-digitales',
                'enrollments' => [
                    ['course_slug' => 'arquitectura-full-stack-con-laravel-y-quasar', 'progress' => 5, 'enrolled_at' => now()->subDays(4)->toDateTimeString(), 'payment_status' => 'completed'],
                ],
            ],
            [
                'profile' => 'newbie',
                'name' => 'Roberto Aliaga',
                'email' => 'roberto.aliaga@plataforma.com',
                'avatar' => 'https://i.pravatar.cc/300?img=18',
                'bio' => 'Se matriculó para explorar automatización y dashboards operativos.',
                'total_points' => 60,
                'current_streak' => 0,
                'last_active_at' => now()->subDays(1),
                'skill_bias' => 'Operaciones',
                'badge_slugs' => [],
                'pending_checkout_course_slug' => 'sistemas-de-informacion-para-nutricion-clinica',
                'enrollments' => [
                    ['course_slug' => 'gestion-de-sistemas-y-observabilidad-operativa', 'progress' => 0, 'enrolled_at' => now()->subDays(3)->toDateTimeString(), 'payment_status' => 'completed'],
                ],
            ],
            [
                'profile' => 'newbie',
                'name' => 'Fernanda Paredes',
                'email' => 'fernanda.paredes@plataforma.com',
                'avatar' => 'https://i.pravatar.cc/300?img=64',
                'bio' => 'Entró por el enfoque clínico y todavía está armando su rutina de estudio.',
                'total_points' => 120,
                'current_streak' => 1,
                'last_active_at' => now()->subHours(12),
                'skill_bias' => 'Análisis',
                'badge_slugs' => [],
                'pending_checkout_course_slug' => 'gestion-de-sistemas-y-observabilidad-operativa',
                'enrollments' => [
                    ['course_slug' => 'informatica-medica-para-flujos-clinicos-digitales', 'progress' => 5, 'enrolled_at' => now()->subDays(2)->toDateTimeString(), 'payment_status' => 'completed'],
                ],
            ],
        ];
    }
}
