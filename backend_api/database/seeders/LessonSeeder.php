<?php

namespace Database\Seeders;

use App\Models\InteractiveConfig;
use App\Models\Lesson;
use App\Models\LessonReading;
use App\Models\LessonResource;
use App\Models\LessonVideo;
use App\Models\Module;
use Illuminate\Database\Seeder;

class LessonSeeder extends Seeder
{
    public function run(): void
    {
        $modules = Module::with('course')->get();

        foreach ($modules as $module) {
            foreach ($this->lessonBlueprints($module->title) as $index => $blueprint) {
                $sortOrder = $index + 1;

                $lesson = Lesson::updateOrCreate(
                    ['module_id' => $module->id, 'sort_order' => $sortOrder],
                    [
                        'title' => $blueprint['title'],
                        'type' => $blueprint['type'],
                        'content_url' => $blueprint['legacy_content_url'] ?? null,
                        'content_text' => $blueprint['legacy_content_text'] ?? null,
                        'duration' => $blueprint['duration'],
                        'is_free' => $blueprint['is_free'],
                        'game_config_id' => null,
                        'quiz_id' => null,
                    ]
                );

                $this->attachPolymorphicContent($lesson, $module, $blueprint);
            }
        }
    }

    private function lessonBlueprints(string $moduleTitle): array
    {
        return [
            [
                'title' => "Introducción en video · {$moduleTitle}",
                'type' => 'video',
                'duration' => 540,
                'is_free' => true,
                'legacy_content_url' => 'https://www.youtube.com/watch?v=ysz5S6PUM-U',
            ],
            [
                'title' => "Guía de lectura · {$moduleTitle}",
                'type' => 'reading',
                'duration' => 720,
                'is_free' => true,
                'legacy_content_text' => $this->readingMarkdown($moduleTitle),
            ],
            [
                'title' => "Recursos descargables · {$moduleTitle}",
                'type' => 'resource',
                'duration' => 300,
                'is_free' => false,
                'legacy_content_url' => 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
            ],
            [
                'title' => "Trivia interactiva · {$moduleTitle}",
                'type' => 'interactive',
                'duration' => 480,
                'is_free' => false,
            ],
            [
                'title' => "Caso práctico en video · {$moduleTitle}",
                'type' => 'video',
                'duration' => 900,
                'is_free' => false,
                'legacy_content_url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ',
            ],
        ];
    }

    private function attachPolymorphicContent(Lesson $lesson, Module $module, array $blueprint): void
    {
        switch ($blueprint['type']) {
            case 'video':
                $content = LessonVideo::updateOrCreate(
                    ['lesson_id' => $lesson->id],
                    [
                        'title' => $lesson->title,
                        'provider' => 'youtube',
                        'video_url' => $blueprint['legacy_content_url'],
                        'embed_url' => $this->toEmbedUrl($blueprint['legacy_content_url']),
                        'duration_seconds' => $blueprint['duration'],
                        'metadata' => [
                            'thumbnail' => 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?auto=format&fit=crop&w=1200&q=80',
                        ],
                    ]
                );

                $lesson->update([
                    'contentable_type' => LessonVideo::class,
                    'contentable_id' => $content->id,
                    'content_url' => $content->video_url,
                    'content_text' => null,
                ]);
                break;

            case 'reading':
                $content = LessonReading::updateOrCreate(
                    ['lesson_id' => $lesson->id],
                    [
                        'title' => $lesson->title,
                        'body_markdown' => $this->readingMarkdown($module->title),
                        'body_html' => null,
                        'estimated_minutes' => max(1, (int) round($blueprint['duration'] / 60)),
                        'metadata' => [
                            'cover_image' => 'https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?auto=format&fit=crop&w=1200&q=80',
                        ],
                    ]
                );

                $lesson->update([
                    'contentable_type' => LessonReading::class,
                    'contentable_id' => $content->id,
                    'content_url' => null,
                    'content_text' => $content->body_markdown,
                ]);
                break;

            case 'resource':
                $content = LessonResource::updateOrCreate(
                    ['lesson_id' => $lesson->id],
                    [
                        'title' => $lesson->title,
                        'file_name' => "recursos-{$module->id}.pdf",
                        'file_url' => $blueprint['legacy_content_url'],
                        'mime_type' => 'application/pdf',
                        'file_size_bytes' => 1048576,
                        'is_downloadable' => true,
                        'metadata' => [
                            'preview_image' => 'https://images.unsplash.com/photo-1517842645767-c639042777db?auto=format&fit=crop&w=1200&q=80',
                        ],
                    ]
                );

                $lesson->update([
                    'contentable_type' => LessonResource::class,
                    'contentable_id' => $content->id,
                    'content_url' => $content->file_url,
                    'content_text' => null,
                ]);
                break;

            case 'interactive':
                $content = InteractiveConfig::updateOrCreate(
                    ['lesson_id' => $lesson->id],
                    [
                        'course_id' => $module->course_id,
                        'module_id' => $module->id,
                        'authoring_mode' => 'form',
                        'activity_type' => 'trivia',
                        'config_payload' => $this->triviaPayload($module->title),
                        'assets_manifest' => [
                            'images' => [
                                'https://images.unsplash.com/photo-1532619675605-1ede6c2ed2b0?auto=format&fit=crop&w=1200&q=80',
                                'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?auto=format&fit=crop&w=1200&q=80',
                            ],
                        ],
                        'source_package_path' => null,
                        'is_active' => true,
                        'version' => 1,
                    ]
                );

                $lesson->update([
                    'contentable_type' => InteractiveConfig::class,
                    'contentable_id' => $content->id,
                    'content_url' => null,
                    'content_text' => null,
                ]);
                break;
        }
    }

    private function readingMarkdown(string $topic): string
    {
        return <<<MD
# {$topic}

En esta lección revisaremos los conceptos clave y una mini guía práctica.

## Objetivos
- Entender el flujo principal del módulo.
- Aplicar buenas prácticas en escenarios reales.
- Prepararte para la actividad interactiva final.

## Recurso visual
![Referencia visual](https://images.unsplash.com/photo-1516116216624-53e697fedbea?auto=format&fit=crop&w=1200&q=80)

> Consejo: toma notas y valida cada paso en tu entorno local.
MD;
    }

    private function triviaPayload(string $moduleTitle): array
    {
        return [
            'title' => "Trivia de repaso · {$moduleTitle}",
            'description' => 'Responde correctamente para ganar XP y reforzar conceptos.',
            'questions' => [
                [
                    'id' => 1,
                    'prompt' => '¿Cuál es la mejor práctica al comenzar un módulo nuevo?',
                    'options' => [
                        ['id' => 'a', 'text' => 'Leer objetivos y preparar entorno', 'is_correct' => true],
                        ['id' => 'b', 'text' => 'Saltar directo al final', 'is_correct' => false],
                        ['id' => 'c', 'text' => 'Ignorar documentación', 'is_correct' => false],
                    ],
                    'points' => 20,
                ],
                [
                    'id' => 2,
                    'prompt' => '¿Qué te ayuda a consolidar el aprendizaje?',
                    'options' => [
                        ['id' => 'a', 'text' => 'Practicar con ejemplos propios', 'is_correct' => true],
                        ['id' => 'b', 'text' => 'Memorizar sin practicar', 'is_correct' => false],
                        ['id' => 'c', 'text' => 'Evitar feedback', 'is_correct' => false],
                    ],
                    'points' => 20,
                ],
                [
                    'id' => 3,
                    'prompt' => '¿Qué conviene hacer al terminar una lección?',
                    'options' => [
                        ['id' => 'a', 'text' => 'Documentar hallazgos clave', 'is_correct' => true],
                        ['id' => 'b', 'text' => 'No registrar nada', 'is_correct' => false],
                        ['id' => 'c', 'text' => 'Cambiar todo sin pruebas', 'is_correct' => false],
                    ],
                    'points' => 20,
                ],
            ],
        ];
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
}
