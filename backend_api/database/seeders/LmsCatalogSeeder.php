<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Course;
use App\Models\GameConfiguration;
use App\Models\GameType;
use App\Models\InteractiveConfig;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Models\LessonVideo;
use App\Models\Module;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class LmsCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect([
            ['name' => 'Desarrollo Full-stack', 'slug' => 'desarrollo-full-stack', 'icon' => 'code'],
            ['name' => 'Informática Médica', 'slug' => 'informatica-medica', 'icon' => 'monitor_heart'],
            ['name' => 'Gestión de Sistemas', 'slug' => 'gestion-de-sistemas', 'icon' => 'settings_suggest'],
            ['name' => 'Nutrición Automatizada', 'slug' => 'nutricion-automatizada', 'icon' => 'restaurant_menu'],
        ])->mapWithKeys(function (array $category) {
            $model = Category::create($category);

            return [$model->slug => $model];
        });

        $instructors = User::where('role', 'instructor')->get()->keyBy('email');
        $triviaType = GameType::where('slug', 'trivia')->firstOrFail();
        $orderingType = GameType::where('slug', 'puzzle')->first()
            ?? GameType::where('slug', 'drag-drop')->firstOrFail();

        foreach ($this->courseBlueprints() as $blueprint) {
            $course = Course::create([
                'title' => $blueprint['title'],
                'slug' => $blueprint['slug'],
                'description' => $blueprint['description'],
                'short_description' => $blueprint['short_description'],
                'price' => $blueprint['price'],
                'thumbnail' => $blueprint['thumbnail'],
                'promo_video' => $blueprint['promo_video'],
                'instructor_id' => $instructors[$blueprint['instructor_email']]->id,
                'category_id' => $categories[$blueprint['category_slug']]->id,
                'level' => $blueprint['level'],
                'minimum_level_required' => $blueprint['minimum_level_required'] ?? 1,
                'language' => 'es',
                'status' => 'published',
                'requirements' => $blueprint['requirements'],
                'what_you_learn' => $blueprint['what_you_learn'],
                'has_certificate' => true,
                'certificate_min_score' => $blueprint['certificate_min_score'],
            ]);

            foreach ($blueprint['modules'] as $moduleIndex => $moduleBlueprint) {
                $module = Module::create([
                    'course_id' => $course->id,
                    'title' => $moduleBlueprint['title'],
                    'description' => $moduleBlueprint['description'],
                    'sort_order' => $moduleIndex + 1,
                ]);

                $this->createVideoLesson($course, $module, 1, $moduleBlueprint, true);
                $this->createVideoLesson($course, $module, 2, $moduleBlueprint, false);
                $this->createResourceLesson($course, $module, 3, $moduleBlueprint);
                $this->createInteractiveLesson(
                    $course,
                    $module,
                    4,
                    $moduleBlueprint,
                    $moduleBlueprint['interactive_mode'],
                    $moduleBlueprint['interactive_mode'] === 'trivia' ? $triviaType : $orderingType
                );
            }
        }
    }

    private function createVideoLesson(Course $course, Module $module, int $sortOrder, array $moduleBlueprint, bool $isFree): void
    {
        $titlePrefix = $sortOrder === 1 ? 'Sesión guiada' : 'Laboratorio aplicado';
        $videoToken = fake()->regexify('[A-Za-z0-9_-]{11}');
        $duration = fake()->numberBetween(640, 1480);

        $lesson = Lesson::create([
            'module_id' => $module->id,
            'title' => $titlePrefix.' · '.$moduleBlueprint['video_focus'][$sortOrder - 1],
            'type' => 'video',
            'content_url' => 'https://www.youtube.com/watch?v='.$videoToken,
            'content_text' => null,
            'duration' => $duration,
            'sort_order' => $sortOrder,
            'is_free' => $isFree,
        ]);

        $content = LessonVideo::create([
            'lesson_id' => $lesson->id,
            'title' => $lesson->title,
            'provider' => 'youtube',
            'video_url' => $lesson->content_url,
            'embed_url' => 'https://www.youtube.com/embed/'.$videoToken,
            'duration_seconds' => $duration,
            'metadata' => [
                'resolution' => '1080p',
                'captions' => ['es'],
                'course_skill_area' => $moduleBlueprint['skill_area'],
            ],
        ]);

        $lesson->update([
            'contentable_type' => LessonVideo::class,
            'contentable_id' => $content->id,
        ]);
    }

    private function createResourceLesson(Course $course, Module $module, int $sortOrder, array $moduleBlueprint): void
    {
        $resourceName = Str::slug($course->slug.'-'.$module->sort_order.'-guia').'.pdf';
        $title = 'Guía PDF · '.$moduleBlueprint['resource_focus'];

        $lesson = Lesson::create([
            'module_id' => $module->id,
            'title' => $title,
            'type' => 'resource',
            'content_url' => 'https://cdn.example.test/materiales/'.$resourceName,
            'content_text' => 'Documento de apoyo, checklist operativo y plantilla de implementación.',
            'duration' => fake()->numberBetween(420, 900),
            'sort_order' => $sortOrder,
            'is_free' => false,
        ]);

        $content = LessonResource::create([
            'lesson_id' => $lesson->id,
            'title' => $title,
            'file_name' => $resourceName,
            'file_url' => $lesson->content_url,
            'mime_type' => 'application/pdf',
            'file_size_bytes' => fake()->numberBetween(500000, 4200000),
            'is_downloadable' => true,
            'metadata' => [
                'pages' => fake()->numberBetween(12, 28),
                'resource_type' => 'pdf',
                'skill_area' => $moduleBlueprint['skill_area'],
            ],
        ]);

        $lesson->update([
            'contentable_type' => LessonResource::class,
            'contentable_id' => $content->id,
        ]);
    }

    private function createInteractiveLesson(
        Course $course,
        Module $module,
        int $sortOrder,
        array $moduleBlueprint,
        string $activityType,
        GameType $gameType
    ): void {
        $payload = $activityType === 'trivia'
            ? $this->buildTriviaPayload($course, $moduleBlueprint)
            : $this->buildOrderingPayload($course, $moduleBlueprint);

        $lesson = Lesson::create([
            'module_id' => $module->id,
            'title' => ($activityType === 'trivia' ? 'Actividad tipo trivia' : 'Actividad de ordenar flujo').' · '.$moduleBlueprint['interactive_title'],
            'type' => 'interactive',
            'content_url' => null,
            'content_text' => null,
            'duration' => fake()->numberBetween(420, 780),
            'sort_order' => $sortOrder,
            'is_free' => false,
        ]);

        $interactiveConfig = InteractiveConfig::create([
            'lesson_id' => $lesson->id,
            'course_id' => $course->id,
            'module_id' => $module->id,
            'authoring_mode' => 'form',
            'activity_type' => $activityType,
            'config_payload' => $payload,
            'assets_manifest' => [
                'theme' => Arr::get($moduleBlueprint, 'visual_theme', 'academic-dark'),
                'skill_area' => $moduleBlueprint['skill_area'],
            ],
            'source_package_path' => 'seeders/'.$course->slug.'/'.$module->sort_order.'/'.$activityType.'.json',
            'is_active' => true,
            'version' => 1,
        ]);

        $gameConfiguration = GameConfiguration::create([
            'title' => 'Juego: '.$lesson->title,
            'game_type_id' => $gameType->id,
            'course_id' => $course->id,
            'module_id' => $module->id,
            'lesson_id' => $lesson->id,
            'config' => [
                'activity_type' => $activityType,
                'skill_area' => $moduleBlueprint['skill_area'],
                'mastery_tags' => $moduleBlueprint['mastery_tags'],
            ],
            'max_score' => 100,
            'time_limit' => $activityType === 'trivia' ? 600 : 480,
            'max_attempts' => 3,
            'is_active' => true,
        ]);

        $quiz = null;

        if ($activityType === 'trivia') {
            $quiz = Quiz::create([
                'title' => 'Quiz · '.$moduleBlueprint['interactive_title'],
                'course_id' => $course->id,
                'module_id' => $module->id,
                'lesson_id' => $lesson->id,
                'description' => 'Evaluación de repaso para validar comprensión aplicada del módulo.',
                'passing_score' => 70,
                'time_limit' => 10,
                'max_attempts' => 3,
                'is_active' => true,
            ]);

            foreach ($payload['questions'] as $questionIndex => $question) {
                $correctOption = collect($question['options'])->firstWhere('is_correct', true);

                Question::create([
                    'quiz_id' => $quiz->id,
                    'question' => $question['prompt'],
                    'type' => 'multiple_choice',
                    'options' => $question['options'],
                    'correct_answer' => $correctOption['id'],
                    'points' => $question['points'],
                    'sort_order' => $questionIndex + 1,
                    'explanation' => 'Respuesta asociada al flujo y criterio operacional esperado en el módulo.',
                ]);
            }
        }

        $lesson->update([
            'contentable_type' => InteractiveConfig::class,
            'contentable_id' => $interactiveConfig->id,
            'game_config_id' => $gameConfiguration->id,
            'quiz_id' => $quiz?->id,
        ]);
    }

    private function buildTriviaPayload(Course $course, array $moduleBlueprint): array
    {
        $questionPrompts = [
            [
                'prompt' => '¿Qué acción protege mejor la trazabilidad del proceso descrito en el módulo?',
                'correct' => 'Registrar eventos con contexto y responsable.',
                'incorrect' => [
                    'Guardar solo el resultado final sin historial.',
                    'Eliminar datos intermedios para simplificar.',
                    'Confiar únicamente en memoria del operador.',
                ],
            ],
            [
                'prompt' => '¿Qué indicador revela aprendizaje aplicado y no solo consumo pasivo?',
                'correct' => 'Resolver correctamente un caso real del flujo.',
                'incorrect' => [
                    'Ver el video a doble velocidad.',
                    'Descargar el PDF sin leerlo.',
                    'Marcar la lección como favorita.',
                ],
            ],
            [
                'prompt' => '¿Cuál es el mejor siguiente paso después de detectar un error recurrente?',
                'correct' => 'Ajustar la regla operativa y medir nuevamente.',
                'incorrect' => [
                    'Ignorarlo si el resto del módulo sale bien.',
                    'Cambiar todos los indicadores a la vez.',
                    'Cerrar el reporte y continuar igual.',
                ],
            ],
        ];

        return [
            'title' => 'Trivia aplicada · '.$moduleBlueprint['interactive_title'],
            'description' => 'Preguntas de repaso ligadas al escenario de '.$course->title.'.',
            'passing_score' => 70,
            'skill_area' => $moduleBlueprint['skill_area'],
            'questions' => collect($questionPrompts)->map(function (array $question, int $index) {
                $options = collect([
                    ['id' => 'a', 'text' => $question['correct'], 'is_correct' => true],
                    ['id' => 'b', 'text' => $question['incorrect'][0], 'is_correct' => false],
                    ['id' => 'c', 'text' => $question['incorrect'][1], 'is_correct' => false],
                    ['id' => 'd', 'text' => $question['incorrect'][2], 'is_correct' => false],
                ])->shuffle()->values()->all();

                return [
                    'id' => $index + 1,
                    'prompt' => $question['prompt'],
                    'options' => $options,
                    'points' => 10,
                    'difficulty' => $index === 0 ? 'medium' : 'hard',
                ];
            })->all(),
        ];
    }

    private function buildOrderingPayload(Course $course, array $moduleBlueprint): array
    {
        $steps = [
            'Recibir el dato o solicitud inicial',
            'Validar reglas críticas y consistencia',
            'Aplicar la automatización o cálculo',
            'Auditar el resultado y publicar evidencia',
        ];

        return [
            'title' => 'Ordena el flujo · '.$moduleBlueprint['interactive_title'],
            'description' => 'Secuencia correctamente el proceso operativo del módulo.',
            'skill_area' => $moduleBlueprint['skill_area'],
            'items' => collect($steps)->map(function (string $step, int $index) use ($course) {
                return [
                    'id' => $index + 1,
                    'text' => $step.' en el contexto de '.$course->short_description,
                    'position' => $index + 1,
                ];
            })->shuffle()->values()->all(),
            'points_per_item' => 25,
        ];
    }

    private function courseBlueprints(): array
    {
        return [
            [
                'title' => 'Arquitectura Full-stack con Laravel y Quasar',
                'slug' => 'arquitectura-full-stack-con-laravel-y-quasar',
                'category_slug' => 'desarrollo-full-stack',
                'instructor_email' => 'profesor@plataforma.com',
                'description' => 'Diseña, construye y opera un producto full-stack con backend en Laravel, frontend en Quasar y flujos integrados de autenticación, observabilidad y despliegue.',
                'short_description' => 'Producto full-stack con Laravel, Quasar y operación real.',
                'price' => 69.99,
                'thumbnail' => 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80',
                'promo_video' => 'https://www.youtube.com/watch?v=2OHbjep_WjQ',
                'level' => 'intermediate',
                'minimum_level_required' => 3,
                'certificate_min_score' => 80,
                'requirements' => ['PHP intermedio', 'JavaScript moderno', 'Conocimiento básico de APIs REST'],
                'what_you_learn' => ['Diseñar módulos backend y frontend conectados', 'Modelar autenticación y estados complejos', 'Operar una plataforma educativa con métricas'],
                'modules' => [
                    [
                        'title' => 'Fundamentos del stack productivo',
                        'description' => 'Mapea la arquitectura, el flujo de dominio y la interfaz inicial del producto.',
                        'video_focus' => ['Arquitectura base y bounded contexts', 'Maquetación del workspace del alumno'],
                        'resource_focus' => 'Checklist de arquitectura y endpoints clave',
                        'interactive_title' => 'Repaso de arquitectura base',
                        'interactive_mode' => 'trivia',
                        'skill_area' => 'Lógica',
                        'mastery_tags' => ['backend', 'frontend', 'arquitectura'],
                        'visual_theme' => 'fullstack-grid',
                    ],
                    [
                        'title' => 'Consumo de APIs y sincronización reactiva',
                        'description' => 'Conecta vistas reactivas con estados globales, stores y validaciones operativas.',
                        'video_focus' => ['Sincronización con Pinia', 'Errores, loading y optimistic UI'],
                        'resource_focus' => 'Mapa de contratos API y estados de pantalla',
                        'interactive_title' => 'Ordena el flujo de sincronización',
                        'interactive_mode' => 'ordering',
                        'skill_area' => 'Lógica',
                        'mastery_tags' => ['pinia', 'api', 'estado'],
                        'visual_theme' => 'flow-builder',
                    ],
                    [
                        'title' => 'Entrega, monitoreo y calidad',
                        'description' => 'Cierra el ciclo con métricas, despliegue y observabilidad para un producto mantenible.',
                        'video_focus' => ['Indicadores de salud del sistema', 'Estrategia de despliegue continuo'],
                        'resource_focus' => 'Runbook de lanzamiento y rollback',
                        'interactive_title' => 'Checklist de release y monitoreo',
                        'interactive_mode' => 'trivia',
                        'skill_area' => 'Operaciones',
                        'mastery_tags' => ['deploy', 'monitoring', 'quality'],
                        'visual_theme' => 'observability-console',
                    ],
                ],
            ],
            [
                'title' => 'Informática Médica para Flujos Clínicos Digitales',
                'slug' => 'informatica-medica-para-flujos-clinicos-digitales',
                'category_slug' => 'informatica-medica',
                'instructor_email' => 'sara.molina@plataforma.com',
                'description' => 'Aprende a modelar procesos clínicos, interoperabilidad y trazabilidad de datos para entornos hospitalarios y ambulatorios.',
                'short_description' => 'Interoperabilidad clínica, trazabilidad y experiencia del paciente.',
                'price' => 74.99,
                'thumbnail' => 'https://images.unsplash.com/photo-1576091160550-2173dba999ef?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80',
                'promo_video' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ',
                'level' => 'advanced',
                'minimum_level_required' => 6,
                'certificate_min_score' => 82,
                'requirements' => ['Conocimientos básicos de procesos hospitalarios', 'Interés por interoperabilidad clínica'],
                'what_you_learn' => ['Diseñar journeys clínicos digitalizados', 'Medir calidad y seguridad en el dato', 'Integrar reglas operativas y trazabilidad'],
                'modules' => [
                    [
                        'title' => 'Datos clínicos y experiencia asistencial',
                        'description' => 'Comprende actores, eventos y puntos de fricción del dato clínico.',
                        'video_focus' => ['Journey del paciente y captura de datos', 'Modelado del episodio clínico digital'],
                        'resource_focus' => 'Mapa de interoperabilidad y campos críticos',
                        'interactive_title' => 'Repaso de trazabilidad clínica',
                        'interactive_mode' => 'trivia',
                        'skill_area' => 'Análisis',
                        'mastery_tags' => ['clinical-data', 'patient-journey', 'traceability'],
                        'visual_theme' => 'medical-grid',
                    ],
                    [
                        'title' => 'Calidad, seguridad y reglas de validación',
                        'description' => 'Diseña controles de consistencia para minimizar errores operativos.',
                        'video_focus' => ['Validaciones de consistencia clínica', 'Alertas y tableros de seguimiento'],
                        'resource_focus' => 'Matriz de riesgos y validaciones',
                        'interactive_title' => 'Ordena el flujo de validación de un caso',
                        'interactive_mode' => 'ordering',
                        'skill_area' => 'Análisis',
                        'mastery_tags' => ['quality', 'patient-safety', 'rules'],
                        'visual_theme' => 'clinical-alerts',
                    ],
                    [
                        'title' => 'Automatización del reporte clínico',
                        'description' => 'Transforma evidencias operativas en tableros útiles para decisiones.',
                        'video_focus' => ['Diseño de reportes clínicos', 'KPIs para gestión hospitalaria'],
                        'resource_focus' => 'Plantilla PDF de indicadores clínicos',
                        'interactive_title' => 'Quiz de indicadores hospitalarios',
                        'interactive_mode' => 'trivia',
                        'skill_area' => 'Negocios',
                        'mastery_tags' => ['kpi', 'reporting', 'clinical-ops'],
                        'visual_theme' => 'hospital-dashboard',
                    ],
                ],
            ],
            [
                'title' => 'Gestión de Sistemas y Observabilidad Operativa',
                'slug' => 'gestion-de-sistemas-y-observabilidad-operativa',
                'category_slug' => 'gestion-de-sistemas',
                'instructor_email' => 'diego.murillo@plataforma.com',
                'description' => 'Construye disciplina operativa para plataformas críticas: monitoreo, incidentes, capacidad y automatización de respuesta.',
                'short_description' => 'Operación, incidentes y observabilidad en plataformas críticas.',
                'price' => 64.99,
                'thumbnail' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80',
                'promo_video' => 'https://www.youtube.com/watch?v=lTTajzrSkCw',
                'level' => 'intermediate',
                'minimum_level_required' => 4,
                'certificate_min_score' => 78,
                'requirements' => ['Fundamentos de redes y servidores', 'Interés por SRE y soporte de sistemas'],
                'what_you_learn' => ['Crear tableros accionables', 'Gestionar incidentes con evidencia', 'Automatizar tareas repetitivas del equipo de soporte'],
                'modules' => [
                    [
                        'title' => 'Salud de plataforma y señales clave',
                        'description' => 'Define métricas, eventos y umbrales que sí explican el estado del sistema.',
                        'video_focus' => ['Golden signals y SLIs', 'Diseño de tableros operativos'],
                        'resource_focus' => 'Plantilla de observabilidad y catálogo de alertas',
                        'interactive_title' => 'Trivia de señales y monitoreo',
                        'interactive_mode' => 'trivia',
                        'skill_area' => 'Operaciones',
                        'mastery_tags' => ['sli', 'slo', 'alerting'],
                        'visual_theme' => 'ops-dashboard',
                    ],
                    [
                        'title' => 'Gestión de incidentes y postmortems',
                        'description' => 'Orquesta respuesta y aprendizaje después de eventos críticos.',
                        'video_focus' => ['Runbooks y escalamiento', 'Postmortems sin culpa'],
                        'resource_focus' => 'Formato PDF de incidente y retrospectiva',
                        'interactive_title' => 'Ordena el flujo de respuesta a incidentes',
                        'interactive_mode' => 'ordering',
                        'skill_area' => 'Operaciones',
                        'mastery_tags' => ['incident-response', 'runbook', 'postmortem'],
                        'visual_theme' => 'incident-room',
                    ],
                    [
                        'title' => 'Capacidad, automatización y mejora continua',
                        'description' => 'Convierte la observabilidad en decisiones de capacidad y automatización.',
                        'video_focus' => ['Forecast de capacidad', 'Automatizaciones de soporte'],
                        'resource_focus' => 'Checklist de automatización operativa',
                        'interactive_title' => 'Quiz de priorización operativa',
                        'interactive_mode' => 'trivia',
                        'skill_area' => 'Negocios',
                        'mastery_tags' => ['capacity', 'automation', 'prioritization'],
                        'visual_theme' => 'capacity-planner',
                    ],
                ],
            ],
            [
                'title' => 'Sistemas de Información para Nutrición Clínica',
                'slug' => 'sistemas-de-informacion-para-nutricion-clinica',
                'category_slug' => 'nutricion-automatizada',
                'instructor_email' => 'anag@plataforma.com',
                'description' => 'Modela procesos, dashboards y automatizaciones para seguimiento nutricional clínico con foco en decisiones basadas en datos.',
                'short_description' => 'Automatización y analítica aplicada a nutrición clínica.',
                'price' => 57.99,
                'thumbnail' => 'https://images.unsplash.com/photo-1498837167922-ddd27525d352?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80',
                'promo_video' => 'https://www.youtube.com/watch?v=ysz5S6PUM-U',
                'level' => 'beginner',
                'minimum_level_required' => 1,
                'certificate_min_score' => 76,
                'requirements' => ['Bases de nutrición o gestión clínica', 'Interés por automatizar seguimiento nutricional'],
                'what_you_learn' => ['Estandarizar registros nutricionales', 'Automatizar tableros de seguimiento', 'Diseñar alertas e indicadores accionables'],
                'modules' => [
                    [
                        'title' => 'Registro nutricional y consistencia del dato',
                        'description' => 'Construye el registro mínimo viable para seguimiento clínico útil.',
                        'video_focus' => ['Variables antropométricas críticas', 'Diseño del formulario nutricional'],
                        'resource_focus' => 'Diccionario PDF de variables nutricionales',
                        'interactive_title' => 'Trivia de registro clínico nutricional',
                        'interactive_mode' => 'trivia',
                        'skill_area' => 'Diseño',
                        'mastery_tags' => ['nutrition-data', 'forms', 'consistency'],
                        'visual_theme' => 'nutrition-forms',
                    ],
                    [
                        'title' => 'Reglas, alertas y seguimiento automatizado',
                        'description' => 'Convierte eventos del paciente en alertas e intervenciones oportunas.',
                        'video_focus' => ['Motor de reglas nutricionales', 'Semaforización de riesgo'],
                        'resource_focus' => 'Matriz PDF de alertas y umbrales',
                        'interactive_title' => 'Ordena el flujo de seguimiento automatizado',
                        'interactive_mode' => 'ordering',
                        'skill_area' => 'Diseño',
                        'mastery_tags' => ['rules', 'alerts', 'follow-up'],
                        'visual_theme' => 'nutrition-alerts',
                    ],
                    [
                        'title' => 'Dashboards y decisiones del equipo clínico',
                        'description' => 'Construye visualizaciones útiles para nutricionistas y jefaturas clínicas.',
                        'video_focus' => ['KPIs de adherencia y evolución', 'Presentación efectiva de resultados'],
                        'resource_focus' => 'Plantilla PDF de tablero nutricional',
                        'interactive_title' => 'Quiz de indicadores nutricionales',
                        'interactive_mode' => 'trivia',
                        'skill_area' => 'Negocios',
                        'mastery_tags' => ['dashboard', 'nutrition-kpis', 'decision-making'],
                        'visual_theme' => 'nutrition-dashboard',
                    ],
                ],
            ],
        ];
    }
}
