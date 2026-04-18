<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\InteractiveConfig;
use App\Models\Lesson;
use App\Models\LessonReading;
use App\Models\LessonResource;
use App\Models\LessonVideo;
use App\Models\Module;
use App\Services\TeacherMediaUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InstructorContentController extends Controller
{
    public function courseStructure(Request $request, Course $course)
    {
        $this->authorizeCourseOwnerOrAdmin($request, $course);

        return response()->json(
            $course->load([
                'category:id,name,slug',
                'modules' => fn ($query) => $query
                    ->with(['lessons' => fn ($lessonQuery) => $lessonQuery
                        ->with(['contentable', 'interactiveConfig'])
                        ->orderBy('sort_order')])
                    ->orderBy('sort_order'),
            ])->loadCount(['modules', 'lessons'])
        );
    }

    public function storeModule(Request $request, Course $course)
    {
        $this->authorizeCourseOwnerOrAdmin($request, $course);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? ((int) $course->modules()->max('sort_order') + 1);

        $module = $course->modules()->create($validated);

        return response()->json($module->fresh()->load('lessons'), 201);
    }

    public function updateModule(Request $request, Module $module)
    {
        $this->authorizeCourseOwnerOrAdmin($request, $module->course);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $module->update($validated);

        return response()->json($module->fresh()->load('lessons'));
    }

    public function destroyModule(Request $request, Module $module)
    {
        $this->authorizeCourseOwnerOrAdmin($request, $module->course);

        $module->delete();

        return response()->json(['message' => 'Módulo eliminado correctamente.']);
    }

    public function storeLesson(Request $request, Module $module)
    {
        $this->authorizeCourseOwnerOrAdmin($request, $module->course);

        $validated = $this->validateLessonPayload($request, true);
        $validated['sort_order'] = $validated['sort_order'] ?? ((int) $module->lessons()->max('sort_order') + 1);

        $lesson = $module->lessons()->create([
            'title' => $validated['title'],
            'type' => $validated['type'],
            'duration' => $validated['duration'] ?? 0,
            'sort_order' => $validated['sort_order'],
            'is_free' => $validated['is_free'] ?? false,
            'content_url' => null,
            'content_text' => null,
            'game_config_id' => null,
            'quiz_id' => null,
        ]);

        $this->syncLessonContent($lesson, $validated);

        return response()->json($this->loadLesson($lesson), 201);
    }

    public function updateLesson(Request $request, Lesson $lesson)
    {
        $lesson->loadMissing('module.course');
        $this->authorizeCourseOwnerOrAdmin($request, $lesson->module->course);

        $validated = $this->validateLessonPayload($request, false);

        $lesson->update(array_filter([
            'title' => $validated['title'] ?? $lesson->title,
            'type' => $validated['type'] ?? $lesson->type,
            'duration' => $validated['duration'] ?? $lesson->duration,
            'sort_order' => $validated['sort_order'] ?? $lesson->sort_order,
            'is_free' => array_key_exists('is_free', $validated) ? $validated['is_free'] : $lesson->is_free,
        ], static fn ($value) => $value !== null));

        $this->syncLessonContent($lesson->fresh(), [
            ...$validated,
            'type' => $validated['type'] ?? $lesson->type,
        ]);

        return response()->json($this->loadLesson($lesson->fresh()));
    }

    public function destroyLesson(Request $request, Lesson $lesson)
    {
        $lesson->loadMissing('module.course');
        $this->authorizeCourseOwnerOrAdmin($request, $lesson->module->course);

        $lesson->delete();

        return response()->json(['message' => 'Lección eliminada correctamente.']);
    }

    private function validateLessonPayload(Request $request, bool $isCreate): array
    {
        $required = $isCreate ? 'required' : 'sometimes';

        return $request->validate([
            'title' => "{$required}|string|max:255",
            'type' => "{$required}|in:video,reading,resource,interactive",
            'duration' => 'nullable|integer|min:0|max:86400',
            'sort_order' => 'nullable|integer|min:0',
            'is_free' => 'sometimes|boolean',
            'content_url' => 'nullable|string|max:2000',
            'content_text' => 'nullable|string',
            'provider' => 'nullable|string|max:100',
            'video_upload_token' => 'nullable|string|max:100',
            'resource_upload_token' => 'nullable|string|max:100',
            'activity_type' => 'nullable|string|max:100',
            'max_attempts' => 'nullable|integer|min:1|max:20',
            'passing_score' => 'nullable|integer|min:0|max:100',
            'xp_reward' => 'nullable|integer|min:0|max:5000',
            'coin_reward' => 'nullable|integer|min:0|max:5000',
            'interactive_config_payload' => 'nullable|array',
            'metadata' => 'nullable|array',
        ]);
    }

    private function syncLessonContent(Lesson $lesson, array $payload): void
    {
        $type = $payload['type'];
        $title = $payload['title'] ?? $lesson->title;
        $metadata = $payload['metadata'] ?? null;
        $uploadService = app(TeacherMediaUploadService::class);

        if ($type === 'video') {
            $videoUploadToken = $payload['video_upload_token'] ?? null;
            $videoProvider = $videoUploadToken ? 'local' : ($payload['provider'] ?? 'external');
            $videoUrl = $payload['content_url'] ?? $lesson->content_url ?? 'https://example.com/video-demo';
            $video = LessonVideo::updateOrCreate(
                ['lesson_id' => $lesson->id],
                [
                    'title' => $title,
                    'provider' => $videoProvider,
                    'video_url' => $videoUrl,
                    'embed_url' => $videoProvider === 'local' ? null : $this->toEmbedUrl($videoUrl),
                    'duration_seconds' => $payload['duration'] ?? $lesson->duration,
                    'metadata' => $metadata,
                ]
            );

            if ($videoUploadToken) {
                $manifest = $uploadService->consumeUpload('video', $videoUploadToken);
                $video->clearMediaCollection('lesson_video');
                $video
                    ->addMedia(Storage::disk('local')->path($manifest['path']))
                    ->usingName($title)
                    ->usingFileName($manifest['file_name'])
                    ->withCustomProperties([
                        'source' => 'teacher_chunk_upload',
                        'mime_type' => $manifest['mime_type'],
                        'size' => $manifest['size'],
                    ])
                    ->toMediaCollection('lesson_video', 'local');

                $video->update([
                    'provider' => 'local',
                    'video_url' => $video->signedStreamUrl(),
                    'embed_url' => null,
                    'metadata' => array_merge($metadata ?? [], [
                        'upload_token' => $videoUploadToken,
                        'mime_type' => $manifest['mime_type'],
                        'file_size_bytes' => $manifest['size'],
                    ]),
                ]);

                $uploadService->cleanupUpload('video', $videoUploadToken);
            }

            $lesson->update([
                'type' => 'video',
                'contentable_type' => LessonVideo::class,
                'contentable_id' => $video->id,
                'content_url' => $video->signedStreamUrl() ?? $video->video_url,
                'content_text' => null,
            ]);

            return;
        }

        if ($type === 'reading') {
            $reading = LessonReading::updateOrCreate(
                ['lesson_id' => $lesson->id],
                [
                    'title' => $title,
                    'body_markdown' => $payload['content_text'] ?? $lesson->content_text,
                    'body_html' => null,
                    'estimated_minutes' => max(1, (int) round(($payload['duration'] ?? $lesson->duration) / 60)),
                    'metadata' => $metadata,
                ]
            );

            $lesson->update([
                'type' => 'reading',
                'contentable_type' => LessonReading::class,
                'contentable_id' => $reading->id,
                'content_url' => null,
                'content_text' => $reading->body_markdown,
            ]);

            return;
        }

        if ($type === 'resource') {
            $resourceUploadToken = $payload['resource_upload_token'] ?? null;
            $resourceUrl = $payload['content_url'] ?? $lesson->content_url ?? 'https://example.com/recurso.pdf';

            $resource = LessonResource::updateOrCreate(
                ['lesson_id' => $lesson->id],
                [
                    'title' => $title,
                    'file_name' => basename(parse_url($resourceUrl, PHP_URL_PATH) ?: "recurso-{$lesson->id}.pdf"),
                    'file_url' => $resourceUrl,
                    'mime_type' => 'application/pdf',
                    'file_size_bytes' => 0,
                    'is_downloadable' => true,
                    'metadata' => $metadata,
                ]
            );

            if ($resourceUploadToken) {
                $manifest = $uploadService->consumeUpload('resource', $resourceUploadToken);
                $resource->clearMediaCollection('lesson_resource');
                $resource
                    ->addMedia(Storage::disk('local')->path($manifest['path']))
                    ->usingName($title)
                    ->usingFileName($manifest['file_name'])
                    ->withCustomProperties([
                        'source' => 'teacher_chunk_upload',
                        'mime_type' => $manifest['mime_type'],
                        'size' => $manifest['size'],
                    ])
                    ->toMediaCollection('lesson_resource', 'local');

                $resource->update([
                    'file_name' => $manifest['file_name'],
                    'file_url' => $resource->signedDownloadUrl(),
                    'mime_type' => $manifest['mime_type'],
                    'file_size_bytes' => $manifest['size'],
                    'metadata' => array_merge($metadata ?? [], [
                        'upload_token' => $resourceUploadToken,
                        'mime_type' => $manifest['mime_type'],
                        'file_size_bytes' => $manifest['size'],
                    ]),
                ]);

                $uploadService->cleanupUpload('resource', $resourceUploadToken);
            }

            $lesson->update([
                'type' => 'resource',
                'contentable_type' => LessonResource::class,
                'contentable_id' => $resource->id,
                'content_url' => $resource->signedDownloadUrl() ?? $resource->file_url,
                'content_text' => null,
            ]);

            return;
        }

        $config = InteractiveConfig::updateOrCreate(
            ['lesson_id' => $lesson->id],
            [
                'course_id' => $lesson->module->course_id,
                'module_id' => $lesson->module_id,
                'authoring_mode' => 'form',
                'activity_type' => $payload['activity_type'] ?? 'trivia',
                'max_attempts' => $payload['max_attempts'] ?? 3,
                'passing_score' => $payload['passing_score'] ?? 70,
                'xp_reward' => $payload['xp_reward'] ?? 100,
                'coin_reward' => $payload['coin_reward'] ?? 25,
                'config_payload' => $payload['interactive_config_payload'] ?? $this->defaultInteractivePayload($title),
                'assets_manifest' => $metadata,
                'source_package_path' => null,
                'is_active' => true,
                'version' => 1,
            ]
        );

        $lesson->update([
            'type' => 'interactive',
            'contentable_type' => InteractiveConfig::class,
            'contentable_id' => $config->id,
            'content_url' => null,
            'content_text' => null,
        ]);
    }

    private function loadLesson(Lesson $lesson): Lesson
    {
        return $lesson->load(['contentable', 'interactiveConfig', 'module.course']);
    }

    private function authorizeCourseOwnerOrAdmin(Request $request, Course $course): void
    {
        $user = $request->user();

        if (! $user->isAdmin() && (int) $course->instructor_id !== (int) $user->id) {
            abort(403, 'No tienes permiso para modificar este curso.');
        }
    }

    private function defaultInteractivePayload(string $title): array
    {
        return [
            'title' => "Actividad interactiva · {$title}",
            'description' => 'Configura las preguntas y respuestas de esta actividad.',
            'questions' => [
                [
                    'id' => 1,
                    'prompt' => 'Pregunta de ejemplo',
                    'options' => [
                        ['id' => 'a', 'text' => 'Respuesta correcta', 'is_correct' => true],
                        ['id' => 'b', 'text' => 'Respuesta distractora', 'is_correct' => false],
                    ],
                    'points' => 10,
                ],
            ],
        ];
    }

    private function toEmbedUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        if (str_contains($url, 'youtube.com/watch')) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
            $videoId = $params['v'] ?? null;

            return $videoId ? "https://www.youtube.com/embed/{$videoId}" : $url;
        }

        if (str_contains($url, 'youtu.be/')) {
            $videoId = basename(parse_url($url, PHP_URL_PATH) ?? '');

            return $videoId ? "https://www.youtube.com/embed/{$videoId}" : $url;
        }

        return $url;
    }
}
