<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $moduleCourseMap = DB::table('modules')->pluck('course_id', 'id');
        $gameTypeMap = DB::table('game_types')->pluck('slug', 'id');

        $lessons = DB::table('lessons')
            ->select('id', 'module_id', 'title', 'type', 'content_url', 'content_text', 'duration', 'game_config_id', 'quiz_id', 'contentable_type', 'contentable_id')
            ->orderBy('id')
            ->get();

        foreach ($lessons as $lesson) {
            if (! empty($lesson->contentable_type) && ! empty($lesson->contentable_id)) {
                continue;
            }

            $normalizedType = $this->normalizeType((string) $lesson->type);
            $contentable = null;

            if ($normalizedType === 'video') {
                $contentable = $this->createVideoContent($lesson);
            } elseif ($normalizedType === 'reading') {
                $contentable = $this->createReadingContent($lesson);
            } elseif ($normalizedType === 'resource') {
                $contentable = $this->createResourceContent($lesson);
            } elseif ($normalizedType === 'interactive') {
                $courseId = $moduleCourseMap[$lesson->module_id] ?? null;
                $contentable = $this->createInteractiveContent($lesson, $courseId, $gameTypeMap);
            } else {
                $contentable = $this->createReadingContent($lesson);
                $normalizedType = 'reading';
            }

            if (! $contentable) {
                continue;
            }

            DB::table('lessons')
                ->where('id', $lesson->id)
                ->update([
                    'type' => $normalizedType,
                    'contentable_type' => $contentable['type'],
                    'contentable_id' => $contentable['id'],
                ]);
        }
    }

    public function down(): void
    {
        DB::table('lessons')->update([
            'contentable_type' => null,
            'contentable_id' => null,
        ]);
    }

    private function normalizeType(string $type): string
    {
        return match ($type) {
            'text' => 'reading',
            'game', 'quiz' => 'interactive',
            default => $type,
        };
    }

    private function createVideoContent(object $lesson): ?array
    {
        if (empty($lesson->content_url)) {
            return null;
        }

        $id = DB::table('lesson_videos')->insertGetId([
            'lesson_id' => $lesson->id,
            'title' => $lesson->title,
            'provider' => $this->detectVideoProvider((string) $lesson->content_url),
            'video_url' => $lesson->content_url,
            'embed_url' => $this->toEmbedUrl((string) $lesson->content_url),
            'duration_seconds' => (int) ($lesson->duration ?? 0),
            'metadata' => json_encode(['source' => 'legacy_lesson']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['type' => 'App\\Models\\LessonVideo', 'id' => $id];
    }

    private function createReadingContent(object $lesson): ?array
    {
        $id = DB::table('lesson_readings')->insertGetId([
            'lesson_id' => $lesson->id,
            'title' => $lesson->title,
            'body_markdown' => $lesson->content_text,
            'body_html' => null,
            'estimated_minutes' => max(1, (int) round(((int) ($lesson->duration ?? 0)) / 60)),
            'metadata' => json_encode(['source' => 'legacy_lesson']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['type' => 'App\\Models\\LessonReading', 'id' => $id];
    }

    private function createResourceContent(object $lesson): ?array
    {
        if (empty($lesson->content_url)) {
            return null;
        }

        $fileName = basename(parse_url((string) $lesson->content_url, PHP_URL_PATH) ?? '');
        $id = DB::table('lesson_resources')->insertGetId([
            'lesson_id' => $lesson->id,
            'title' => $lesson->title,
            'file_name' => $fileName ?: null,
            'file_url' => $lesson->content_url,
            'mime_type' => null,
            'file_size_bytes' => null,
            'is_downloadable' => true,
            'metadata' => json_encode(['source' => 'legacy_lesson']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['type' => 'App\\Models\\LessonResource', 'id' => $id];
    }

    private function createInteractiveContent(object $lesson, ?int $courseId, $gameTypeMap): ?array
    {
        $activityType = 'trivia';
        $payload = [
            'mode' => 'legacy',
            'title' => $lesson->title,
            'blocks' => [],
        ];

        if (! empty($lesson->game_config_id)) {
            $gameConfig = DB::table('game_configurations')->where('id', $lesson->game_config_id)->first();
            if ($gameConfig) {
                $activityType = $gameTypeMap[$gameConfig->game_type_id] ?? 'custom';
                $payload = [
                    'mode' => 'legacy_game_configuration',
                    'title' => $gameConfig->title ?? $lesson->title,
                    'config' => $this->decodeJson($gameConfig->config),
                    'rules' => [
                        'max_score' => $gameConfig->max_score,
                        'time_limit' => $gameConfig->time_limit,
                        'max_attempts' => $gameConfig->max_attempts,
                    ],
                ];
            }
        } elseif (! empty($lesson->quiz_id)) {
            $quiz = DB::table('quizzes')->where('id', $lesson->quiz_id)->first();
            $questions = DB::table('questions')
                ->where('quiz_id', $lesson->quiz_id)
                ->orderBy('sort_order')
                ->get(['id', 'question', 'type', 'options', 'correct_answer', 'points', 'explanation']);

            $activityType = 'trivia';
            $payload = [
                'mode' => 'legacy_quiz',
                'title' => $quiz->title ?? $lesson->title,
                'description' => $quiz->description ?? null,
                'time_limit' => $quiz->time_limit ?? null,
                'passing_score' => $quiz->passing_score ?? 70,
                'questions' => $questions->map(function ($question) {
                    return [
                        'id' => $question->id,
                        'prompt' => $question->question,
                        'type' => $question->type,
                        'options' => $this->decodeJson($question->options),
                        'correct_answer' => $question->correct_answer,
                        'points' => $question->points,
                        'explanation' => $question->explanation,
                    ];
                })->values()->all(),
            ];
        }

        $id = DB::table('interactive_configs')->insertGetId([
            'lesson_id' => $lesson->id,
            'course_id' => $courseId,
            'module_id' => $lesson->module_id,
            'authoring_mode' => 'form',
            'activity_type' => $activityType,
            'config_payload' => json_encode($payload),
            'assets_manifest' => null,
            'source_package_path' => null,
            'is_active' => true,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['type' => 'App\\Models\\InteractiveConfig', 'id' => $id];
    }

    private function decodeJson($value): mixed
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return json_decode($value, true);
    }

    private function detectVideoProvider(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        if (str_contains($host, 'youtube.com') || str_contains($host, 'youtu.be')) {
            return 'youtube';
        }

        if (str_contains($host, 'vimeo.com')) {
            return 'vimeo';
        }

        return 'external';
    }

    private function toEmbedUrl(string $url): ?string
    {
        if (str_contains($url, 'youtube.com/watch')) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
            $videoId = $params['v'] ?? null;
            if ($videoId) {
                return "https://www.youtube.com/embed/{$videoId}";
            }
        }

        if (str_contains($url, 'youtu.be/')) {
            $videoId = basename(parse_url($url, PHP_URL_PATH) ?? '');
            if ($videoId) {
                return "https://www.youtube.com/embed/{$videoId}";
            }
        }

        return null;
    }
};

