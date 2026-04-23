<?php

namespace Database\Seeders;

use App\Models\ActivityAttempt;
use App\Models\ActivityLog;
use App\Models\AdminActivityLog;
use App\Models\Category;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Comment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GameConfiguration;
use App\Models\GameType;
use App\Models\InteractiveActivityResult;
use App\Models\InteractiveConfig;
use App\Models\Lesson;
use App\Models\LessonReading;
use App\Models\LessonResource;
use App\Models\LessonVideo;
use App\Models\Module;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\PlatformSetting;
use App\Models\Purchase;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\ShopItem;
use App\Models\User;
use App\Models\UserCoupon;
use App\Models\UserItem;
use App\Models\UserLessonProgress;
use App\Models\UserProfile;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoScenarioSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@plataforma.com')->first();

        if (! $admin) {
            return;
        }

        $this->seedPlatformSettings();

        $teachers = User::query()
            ->whereIn('email', [
                'profesor@plataforma.com',
                'anag@plataforma.com',
                'sara.molina@plataforma.com',
                'diego.murillo@plataforma.com',
            ])
            ->get()
            ->keyBy('email');

        $this->configureBasePublishedCourses($admin);
        $this->seedAdditionalShopItems($teachers);
        $scenarioCourses = $this->seedScenarioCourses($teachers, $admin);
        $scenarioStudents = $this->seedScenarioStudents();
        $this->seedUserProfiles($scenarioStudents);
        $this->seedTeacherPayouts($teachers, $admin);
        $this->seedInventoryAndCoupons($scenarioStudents);
        $this->seedStudentJourneys($scenarioStudents, $scenarioCourses, $admin);
        $this->seedCommentThreads($scenarioStudents, $scenarioCourses);
        $this->seedAdminLogs($admin, $scenarioCourses, $teachers);
    }

    private function seedPlatformSettings(): void
    {
        PlatformSetting::putValue(
            'finance.platform_commission_percentage',
            25,
            'finance',
            'number',
            'Comision global de la plataforma sobre ventas y retiros.'
        );

        PlatformSetting::putValue(
            'gamification.levels',
            [
                ['level' => 1, 'xp_required' => 0, 'title' => 'Aprendiz'],
                ['level' => 2, 'xp_required' => 200, 'title' => 'Explorador'],
                ['level' => 3, 'xp_required' => 450, 'title' => 'Explorador'],
                ['level' => 4, 'xp_required' => 700, 'title' => 'Veterano'],
                ['level' => 5, 'xp_required' => 1000, 'title' => 'Veterano'],
                ['level' => 6, 'xp_required' => 1300, 'title' => 'Veterano'],
                ['level' => 7, 'xp_required' => 1650, 'title' => 'Experto'],
                ['level' => 8, 'xp_required' => 2050, 'title' => 'Experto'],
                ['level' => 9, 'xp_required' => 2500, 'title' => 'Maestro'],
                ['level' => 10, 'xp_required' => 3000, 'title' => 'Maestro'],
                ['level' => 11, 'xp_required' => 3600, 'title' => 'Arquitecto'],
                ['level' => 12, 'xp_required' => 4300, 'title' => 'Arquitecto'],
            ],
            'gamification',
            'json',
            'Curva de niveles para escenarios demo y QA.'
        );

        PlatformSetting::putValue(
            'storage.video_monitor',
            [
                'capacity_gb' => 250,
                'used_gb' => 148,
                'used_percentage' => 59.2,
                'warning_threshold' => 75,
            ],
            'storage',
            'json',
            'Indicador demo para el monitor de almacenamiento del administrador.'
        );
    }

    private function configureBasePublishedCourses(User $admin): void
    {
        $publishedAt = now()->subDays(18);

        Course::query()
            ->where('slug', 'arquitectura-full-stack-con-laravel-y-quasar')
            ->update([
                'status' => 'published',
                'approved_by' => $admin->id,
                'published_at' => $publishedAt,
                'submitted_for_review_at' => $publishedAt->copy()->subDays(2),
                'review_notes' => 'Aprobado por curacion tecnica y checklist de videos.',
                'has_certificate' => true,
                'certificate_requires_final_exam' => false,
                'certificate_exam_scope' => 'course',
                'certificate_min_score' => 80,
            ]);

        Course::query()
            ->where('slug', 'informatica-medica-para-flujos-clinicos-digitales')
            ->update([
                'status' => 'published',
                'approved_by' => $admin->id,
                'published_at' => $publishedAt->copy()->subDays(6),
                'submitted_for_review_at' => $publishedAt->copy()->subDays(8),
                'review_notes' => 'Aprobado con observaciones menores resueltas.',
                'has_certificate' => true,
                'certificate_requires_final_exam' => false,
                'certificate_exam_scope' => 'course',
                'certificate_min_score' => 82,
            ]);

        Course::query()
            ->where('slug', 'sistemas-de-informacion-para-nutricion-clinica')
            ->update([
                'status' => 'published',
                'approved_by' => $admin->id,
                'published_at' => $publishedAt->copy()->subDays(4),
                'submitted_for_review_at' => $publishedAt->copy()->subDays(7),
                'review_notes' => 'Curso certificado sin examen final obligatorio.',
                'has_certificate' => true,
                'certificate_requires_final_exam' => false,
                'certificate_exam_scope' => 'course',
                'certificate_min_score' => 76,
            ]);

        Course::query()
            ->where('slug', 'gestion-de-sistemas-y-observabilidad-operativa')
            ->update([
                'status' => 'published',
                'approved_by' => $admin->id,
                'published_at' => $publishedAt->copy()->subDays(2),
                'submitted_for_review_at' => $publishedAt->copy()->subDays(5),
                'review_notes' => 'Curso operativo aprobado sin certificacion.',
                'has_certificate' => false,
                'certificate_requires_final_exam' => false,
                'certificate_exam_scope' => 'course',
            ]);
    }

    private function seedAdditionalShopItems($teachers): void
    {
        $authorId = $teachers->get('profesor@plataforma.com')?->id;

        $items = [
            [
                'name' => 'Marco Avatar Solar',
                'slug' => 'marco-avatar-solar',
                'description' => 'Marco dorado para destacar perfiles con alto avance.',
                'type' => 'avatar_frame',
                'cost_coins' => 220,
                'minimum_level_required' => 4,
                'metadata' => [
                    'frame_class' => 'frame-solar',
                    'accent_color' => '#f7c948',
                ],
            ],
            [
                'name' => 'Marco Avatar Obsidiana',
                'slug' => 'marco-avatar-obsidiana',
                'description' => 'Marco oscuro con acento cian para perfiles elite.',
                'type' => 'avatar_frame',
                'cost_coins' => 260,
                'minimum_level_required' => 5,
                'metadata' => [
                    'frame_class' => 'frame-obsidian',
                    'accent_color' => '#23d5ec',
                ],
            ],
            [
                'name' => 'Titulo Arquitecto QA',
                'slug' => 'titulo-arquitecto-qa',
                'description' => 'Titulo especial para perfiles usados en pruebas integrales.',
                'type' => 'profile_title',
                'cost_coins' => 300,
                'minimum_level_required' => 6,
                'metadata' => [
                    'title' => 'Arquitecto QA',
                    'title_color' => '#7c5cff',
                ],
            ],
            [
                'name' => 'Titulo Comentador Activo',
                'slug' => 'titulo-comentador-activo',
                'description' => 'Destaca a estudiantes participativos en comentarios y foros.',
                'type' => 'profile_title',
                'cost_coins' => 180,
                'minimum_level_required' => 3,
                'metadata' => [
                    'title' => 'Comentador activo',
                    'title_color' => '#20d5ec',
                ],
            ],
        ];

        foreach ($items as $item) {
            ShopItem::query()->updateOrCreate(
                ['slug' => $item['slug']],
                array_merge($item, [
                    'created_by' => $authorId,
                    'is_active' => true,
                ])
            );
        }
    }

    private function seedScenarioCourses($teachers, User $admin): array
    {
        $categories = Category::query()->get()->keyBy('slug');
        $triviaType = GameType::query()->where('slug', 'trivia')->first();
        $puzzleType = GameType::query()
            ->whereIn('slug', ['puzzle', 'drag-drop'])
            ->first();

        $examCourse = $this->upsertScenarioCourse(
            [
                'title' => 'Certificacion DevOps con Examen Final',
                'slug' => 'certificacion-devops-con-examen-final',
                'description' => 'Curso QA con ruta completa de certificacion: videos, lecturas, recursos, actividades y examen final obligatorio.',
                'short_description' => 'Ruta completa con examen final y certificado automatico.',
                'price' => 84.99,
                'thumbnail' => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1200&q=80',
                'promo_video' => 'https://www.youtube.com/watch?v=ysz5S6PUM-U',
                'instructor_id' => $teachers->get('diego.murillo@plataforma.com')?->id,
                'category_id' => $categories->get('gestion-de-sistemas')?->id,
                'level' => 'advanced',
                'language' => 'es',
                'status' => 'published',
                'submitted_for_review_at' => now()->subDays(15),
                'published_at' => now()->subDays(13),
                'approved_by' => $admin->id,
                'review_notes' => 'Aprobado para demo de certificacion con examen final.',
                'requirements' => ['Conocimientos base de sistemas', 'Interes por QA funcional'],
                'what_you_learn' => ['Resolver actividades variadas', 'Completar examen final y certificarte'],
                'has_certificate' => true,
                'certificate_requires_final_exam' => true,
                'certificate_exam_scope' => 'lesson',
                'certificate_min_score' => 80,
                'minimum_level_required' => 4,
            ],
            [
                [
                    'title' => 'Fundamentos operativos del curso demo',
                    'description' => 'Modulo con mezcla de formatos para prueba integral.',
                    'lessons' => [
                        [
                            'key' => 'intro_video',
                            'type' => 'video',
                            'title' => 'Video inicial del escenario QA',
                            'duration' => 780,
                            'is_free' => true,
                            'provider' => 'youtube',
                            'video_url' => 'https://www.youtube.com/watch?v=lTTajzrSkCw',
                            'embed_url' => 'https://www.youtube.com/embed/lTTajzrSkCw',
                            'metadata' => ['quality' => '1080p', 'seed_tag' => 'qa_intro_video'],
                        ],
                        [
                            'key' => 'guia_lectura',
                            'type' => 'reading',
                            'title' => 'Lectura guiada del flujo DevOps',
                            'duration' => 480,
                            'content_text' => 'Lectura con puntos de control del flujo principal.',
                            'body_markdown' => "## Checklist de flujo\n\n- Observar logs\n- Validar pipeline\n- Confirmar despliegue\n- Registrar evidencia",
                            'estimated_minutes' => 8,
                            'metadata' => ['summary' => 'Lectura base para comentarios y seguimiento.'],
                        ],
                        [
                            'key' => 'checklist_pdf',
                            'type' => 'resource',
                            'title' => 'Checklist descargable para validacion',
                            'duration' => 240,
                            'content_text' => 'Documento de apoyo con pasos de curacion y release.',
                            'file_name' => 'checklist-certificacion-devops.pdf',
                            'file_url' => 'https://cdn.example.test/demo/checklist-certificacion-devops.pdf',
                            'mime_type' => 'application/pdf',
                            'file_size_bytes' => 1400000,
                            'metadata' => ['resource_type' => 'pdf', 'description' => 'Checklist descargable para la presentacion y QA.'],
                        ],
                        [
                            'key' => 'matching_flow',
                            'type' => 'interactive',
                            'title' => 'Relaciona conceptos del pipeline',
                            'duration' => 420,
                            'activity_type' => 'matching',
                            'max_attempts' => 3,
                            'passing_score' => 70,
                            'xp_reward' => 120,
                            'coin_reward' => 30,
                            'game_type_id' => $puzzleType?->id,
                            'payload' => $this->matchingPayload(),
                        ],
                    ],
                ],
                [
                    'title' => 'Cierre de certificacion',
                    'description' => 'Incluye actividad de tablero y examen final.',
                    'lessons' => [
                        [
                            'key' => 'crossword_board',
                            'type' => 'interactive',
                            'title' => 'Crucigrama de observabilidad',
                            'duration' => 360,
                            'activity_type' => 'crossword',
                            'max_attempts' => 2,
                            'passing_score' => 80,
                            'xp_reward' => 150,
                            'coin_reward' => 35,
                            'game_type_id' => $puzzleType?->id,
                            'payload' => $this->crosswordPayload(),
                        ],
                        [
                            'key' => 'final_exam',
                            'type' => 'interactive',
                            'title' => 'Examen final de certificacion',
                            'duration' => 600,
                            'activity_type' => 'trivia',
                            'max_attempts' => 2,
                            'passing_score' => 80,
                            'xp_reward' => 220,
                            'coin_reward' => 60,
                            'game_type_id' => $triviaType?->id,
                            'payload' => $this->finalExamPayload(),
                            'quiz_title' => 'Examen final certificado',
                            'quiz_description' => 'Examen final obligatorio para emitir el certificado.',
                            'quiz_questions' => $this->finalExamQuestions(),
                        ],
                    ],
                ],
            ]
        );

        $noCertCourse = $this->upsertScenarioCourse(
            [
                'title' => 'Automatizacion Clinica sin Certificado',
                'slug' => 'automatizacion-clinica-sin-certificado',
                'description' => 'Curso publicado para probar compras y progreso sin motor de certificados.',
                'short_description' => 'Curso publicado sin certificado para pruebas de flujo.',
                'price' => 54.90,
                'thumbnail' => 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?auto=format&fit=crop&w=1200&q=80',
                'promo_video' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ',
                'instructor_id' => $teachers->get('anag@plataforma.com')?->id,
                'category_id' => $categories->get('nutricion-automatizada')?->id,
                'level' => 'beginner',
                'language' => 'es',
                'status' => 'published',
                'submitted_for_review_at' => now()->subDays(10),
                'published_at' => now()->subDays(8),
                'approved_by' => $admin->id,
                'review_notes' => 'Aprobado como curso comercial sin certificado.',
                'requirements' => ['Sin prerequisitos'],
                'what_you_learn' => ['Comprar un curso', 'Marcar avance en video, lectura y recurso'],
                'has_certificate' => false,
                'certificate_requires_final_exam' => false,
                'certificate_exam_scope' => 'course',
                'certificate_min_score' => 70,
                'minimum_level_required' => 1,
            ],
            [
                [
                    'title' => 'Base del curso sin certificado',
                    'description' => 'Modulo unico para probar el journey simple.',
                    'lessons' => [
                        [
                            'key' => 'video_demo',
                            'type' => 'video',
                            'title' => 'Video demo del curso sin certificado',
                            'duration' => 540,
                            'is_free' => true,
                            'provider' => 'youtube',
                            'video_url' => 'https://www.youtube.com/watch?v=2OHbjep_WjQ',
                            'embed_url' => 'https://www.youtube.com/embed/2OHbjep_WjQ',
                            'metadata' => ['quality' => '720p'],
                        ],
                        [
                            'key' => 'recurso_guia',
                            'type' => 'resource',
                            'title' => 'Guia PDF del curso sin certificado',
                            'duration' => 180,
                            'content_text' => 'Guia rapida para identificar el curso en marketplace.',
                            'file_name' => 'guia-curso-sin-certificado.pdf',
                            'file_url' => 'https://cdn.example.test/demo/guia-curso-sin-certificado.pdf',
                            'mime_type' => 'application/pdf',
                            'file_size_bytes' => 880000,
                            'metadata' => ['resource_type' => 'pdf', 'description' => 'Recurso simple para flujo de progreso.'],
                        ],
                        [
                            'key' => 'trivia_basica',
                            'type' => 'interactive',
                            'title' => 'Trivia basica del curso',
                            'duration' => 300,
                            'activity_type' => 'trivia',
                            'max_attempts' => 3,
                            'passing_score' => 70,
                            'xp_reward' => 90,
                            'coin_reward' => 15,
                            'game_type_id' => $triviaType?->id,
                            'payload' => $this->basicTriviaPayload(),
                            'quiz_title' => 'Trivia del curso sin certificado',
                            'quiz_description' => 'Evaluacion corta del recorrido sin certificado.',
                            'quiz_questions' => $this->basicTriviaQuestions(),
                        ],
                    ],
                ],
            ]
        );

        $pendingCourse = $this->upsertScenarioCourse(
            [
                'title' => 'Curso Pendiente de Curacion QA',
                'slug' => 'curso-pendiente-de-curacion-qa',
                'description' => 'Curso en estado pending para validar bandeja de aprobacion admin.',
                'short_description' => 'Pendiente de revision del administrador.',
                'price' => 49.00,
                'thumbnail' => 'https://images.unsplash.com/photo-1516321497487-e288fb19713f?auto=format&fit=crop&w=1200&q=80',
                'promo_video' => 'https://www.youtube.com/watch?v=ysz5S6PUM-U',
                'instructor_id' => $teachers->get('profesor@plataforma.com')?->id,
                'category_id' => $categories->get('desarrollo-full-stack')?->id,
                'level' => 'intermediate',
                'language' => 'es',
                'status' => 'pending',
                'submitted_for_review_at' => now()->subDays(2),
                'published_at' => null,
                'approved_by' => null,
                'review_notes' => 'Esperando validacion de videos, breadcrumbs y UX.',
                'requirements' => ['Curso preparado por docente para revision'],
                'what_you_learn' => ['Escenario de aprobacion admin'],
                'has_certificate' => true,
                'certificate_requires_final_exam' => false,
                'certificate_exam_scope' => 'course',
                'certificate_min_score' => 75,
                'minimum_level_required' => 2,
            ],
            [
                [
                    'title' => 'Modulo enviado a revision',
                    'description' => 'Contenido minimo para validar preview del admin.',
                    'lessons' => [
                        [
                            'key' => 'pending_video',
                            'type' => 'video',
                            'title' => 'Video pendiente de aprobacion',
                            'duration' => 420,
                            'is_free' => true,
                            'provider' => 'youtube',
                            'video_url' => 'https://www.youtube.com/watch?v=lTTajzrSkCw',
                            'embed_url' => 'https://www.youtube.com/embed/lTTajzrSkCw',
                            'metadata' => ['quality' => '1080p'],
                        ],
                        [
                            'key' => 'pending_trivia',
                            'type' => 'interactive',
                            'title' => 'Trivia lista para inspeccion',
                            'duration' => 240,
                            'activity_type' => 'trivia',
                            'max_attempts' => 3,
                            'passing_score' => 70,
                            'xp_reward' => 100,
                            'coin_reward' => 20,
                            'game_type_id' => $triviaType?->id,
                            'payload' => $this->basicTriviaPayload(),
                            'quiz_title' => 'Trivia pendiente',
                            'quiz_description' => 'Evaluacion breve para revision admin.',
                            'quiz_questions' => $this->basicTriviaQuestions(),
                        ],
                    ],
                ],
            ]
        );

        $draftCourse = $this->upsertScenarioCourse(
            [
                'title' => 'Curso Borrador Demo Docente',
                'slug' => 'curso-borrador-demo-docente',
                'description' => 'Curso en borrador listo para seguir editando desde el panel docente.',
                'short_description' => 'Escenario draft editable.',
                'price' => 39.00,
                'thumbnail' => 'https://images.unsplash.com/photo-1516321310764-1fdbf6bd2c99?auto=format&fit=crop&w=1200&q=80',
                'promo_video' => null,
                'instructor_id' => $teachers->get('profesor@plataforma.com')?->id,
                'category_id' => $categories->get('desarrollo-full-stack')?->id,
                'level' => 'beginner',
                'language' => 'es',
                'status' => 'draft',
                'submitted_for_review_at' => null,
                'published_at' => null,
                'approved_by' => null,
                'review_notes' => 'Todavia en construccion.',
                'requirements' => ['Ninguno'],
                'what_you_learn' => ['Editar modulos, lecciones y portada'],
                'has_certificate' => false,
                'certificate_requires_final_exam' => false,
                'certificate_exam_scope' => 'course',
                'certificate_min_score' => 70,
                'minimum_level_required' => 1,
            ],
            [
                [
                    'title' => 'Borrador de modulo inicial',
                    'description' => 'Modulo simple para validacion de course builder.',
                    'lessons' => [
                        [
                            'key' => 'draft_reading',
                            'type' => 'reading',
                            'title' => 'Lectura en construccion',
                            'duration' => 180,
                            'content_text' => 'Borrador del texto de la leccion.',
                            'body_markdown' => "## Borrador\n\nEste contenido esta en progreso.",
                            'estimated_minutes' => 4,
                            'metadata' => ['summary' => 'Lectura simple en borrador.'],
                        ],
                    ],
                ],
            ]
        );

        $rejectedCourse = $this->upsertScenarioCourse(
            [
                'title' => 'Curso Observado por Curacion',
                'slug' => 'curso-observado-por-curacion',
                'description' => 'Escenario de curso rechazado funcionalmente, representado como draft con observaciones del admin.',
                'short_description' => 'Curso observado con notas de rechazo.',
                'price' => 45.00,
                'thumbnail' => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=1200&q=80',
                'promo_video' => null,
                'instructor_id' => $teachers->get('sara.molina@plataforma.com')?->id,
                'category_id' => $categories->get('informatica-medica')?->id,
                'level' => 'intermediate',
                'language' => 'es',
                'status' => 'draft',
                'submitted_for_review_at' => now()->subDays(5),
                'published_at' => null,
                'approved_by' => null,
                'review_notes' => 'Rechazado en curacion: faltan portada, descripcion de recursos y calibracion de XP.',
                'requirements' => ['Pendiente de ajustes'],
                'what_you_learn' => ['Escenario de rechazo y correccion'],
                'has_certificate' => true,
                'certificate_requires_final_exam' => false,
                'certificate_exam_scope' => 'course',
                'certificate_min_score' => 70,
                'minimum_level_required' => 2,
            ],
            [
                [
                    'title' => 'Modulo observado',
                    'description' => 'Contenido con observaciones para el workflow admin.',
                    'lessons' => [
                        [
                            'key' => 'rejected_video',
                            'type' => 'video',
                            'title' => 'Video con observaciones',
                            'duration' => 300,
                            'is_free' => false,
                            'provider' => 'youtube',
                            'video_url' => 'https://www.youtube.com/watch?v=2OHbjep_WjQ',
                            'embed_url' => 'https://www.youtube.com/embed/2OHbjep_WjQ',
                            'metadata' => ['quality' => '720p'],
                        ],
                    ],
                ],
            ]
        );

        /** @var Course $examModel */
        $examModel = $examCourse['course'];
        $examModel->forceFill([
            'certificate_final_lesson_id' => $examCourse['lessons']['final_exam']->id ?? null,
        ])->save();

        return [
            'exam_course' => $examModel->fresh(['modules.lessons.interactiveConfig']),
            'no_cert_course' => $noCertCourse['course']->fresh(['modules.lessons.interactiveConfig']),
            'pending_course' => $pendingCourse['course']->fresh(['modules.lessons.interactiveConfig']),
            'draft_course' => $draftCourse['course']->fresh(['modules.lessons.interactiveConfig']),
            'rejected_course' => $rejectedCourse['course']->fresh(['modules.lessons.interactiveConfig']),
            'nutrition_course' => Course::query()->where('slug', 'sistemas-de-informacion-para-nutricion-clinica')->first(),
            'fullstack_course' => Course::query()->where('slug', 'arquitectura-full-stack-con-laravel-y-quasar')->first(),
            'ops_course' => Course::query()->where('slug', 'gestion-de-sistemas-y-observabilidad-operativa')->first(),
        ];
    }

    private function upsertScenarioCourse(array $courseData, array $moduleBlueprints): array
    {
        $course = Course::query()->updateOrCreate(
            ['slug' => $courseData['slug']],
            $courseData
        );

        $lessonRegistry = [];

        foreach ($moduleBlueprints as $moduleIndex => $moduleBlueprint) {
            $module = Module::query()->updateOrCreate(
                [
                    'course_id' => $course->id,
                    'sort_order' => $moduleIndex + 1,
                ],
                [
                    'title' => $moduleBlueprint['title'],
                    'description' => $moduleBlueprint['description'] ?? null,
                ]
            );

            foreach ($moduleBlueprint['lessons'] as $lessonIndex => $lessonBlueprint) {
                $lesson = Lesson::query()->updateOrCreate(
                    [
                        'module_id' => $module->id,
                        'sort_order' => $lessonIndex + 1,
                    ],
                    [
                        'title' => $lessonBlueprint['title'],
                        'type' => $lessonBlueprint['type'],
                        'content_url' => $lessonBlueprint['video_url'] ?? $lessonBlueprint['file_url'] ?? null,
                        'content_text' => $lessonBlueprint['content_text'] ?? null,
                        'duration' => $lessonBlueprint['duration'] ?? 300,
                        'is_free' => (bool) ($lessonBlueprint['is_free'] ?? false),
                    ]
                );

                if ($lessonBlueprint['type'] === 'video') {
                    $video = LessonVideo::query()->updateOrCreate(
                        ['lesson_id' => $lesson->id],
                        [
                            'title' => $lessonBlueprint['title'],
                            'provider' => $lessonBlueprint['provider'] ?? 'youtube',
                            'video_url' => $lessonBlueprint['video_url'],
                            'embed_url' => $lessonBlueprint['embed_url'] ?? null,
                            'duration_seconds' => $lessonBlueprint['duration'] ?? null,
                            'metadata' => $lessonBlueprint['metadata'] ?? [],
                        ]
                    );

                    $lesson->forceFill([
                        'contentable_type' => LessonVideo::class,
                        'contentable_id' => $video->id,
                        'quiz_id' => null,
                    ])->save();
                }

                if ($lessonBlueprint['type'] === 'reading') {
                    $reading = LessonReading::query()->updateOrCreate(
                        ['lesson_id' => $lesson->id],
                        [
                            'title' => $lessonBlueprint['title'],
                            'body_markdown' => $lessonBlueprint['body_markdown'] ?? null,
                            'body_html' => $lessonBlueprint['body_html'] ?? null,
                            'estimated_minutes' => $lessonBlueprint['estimated_minutes'] ?? null,
                            'metadata' => $lessonBlueprint['metadata'] ?? [],
                        ]
                    );

                    $lesson->forceFill([
                        'contentable_type' => LessonReading::class,
                        'contentable_id' => $reading->id,
                        'quiz_id' => null,
                    ])->save();
                }

                if ($lessonBlueprint['type'] === 'resource') {
                    $resource = LessonResource::query()->updateOrCreate(
                        ['lesson_id' => $lesson->id],
                        [
                            'title' => $lessonBlueprint['title'],
                            'file_name' => $lessonBlueprint['file_name'] ?? null,
                            'file_url' => $lessonBlueprint['file_url'],
                            'mime_type' => $lessonBlueprint['mime_type'] ?? 'application/pdf',
                            'file_size_bytes' => $lessonBlueprint['file_size_bytes'] ?? null,
                            'is_downloadable' => true,
                            'metadata' => $lessonBlueprint['metadata'] ?? [],
                        ]
                    );

                    $lesson->forceFill([
                        'contentable_type' => LessonResource::class,
                        'contentable_id' => $resource->id,
                        'quiz_id' => null,
                    ])->save();
                }

                if ($lessonBlueprint['type'] === 'interactive') {
                    $interactiveConfig = InteractiveConfig::query()->updateOrCreate(
                        ['lesson_id' => $lesson->id],
                        [
                            'course_id' => $course->id,
                            'module_id' => $module->id,
                            'authoring_mode' => 'form',
                            'activity_type' => $lessonBlueprint['activity_type'],
                            'max_attempts' => $lessonBlueprint['max_attempts'] ?? 3,
                            'passing_score' => $lessonBlueprint['passing_score'] ?? 70,
                            'xp_reward' => $lessonBlueprint['xp_reward'] ?? 100,
                            'coin_reward' => $lessonBlueprint['coin_reward'] ?? 25,
                            'config_payload' => $lessonBlueprint['payload'],
                            'assets_manifest' => [
                                'seed' => true,
                                'theme' => 'qa-demo',
                            ],
                            'source_package_path' => 'seeders/demo-scenarios/'.$course->slug.'/'.$lesson->sort_order.'.json',
                            'is_active' => true,
                            'version' => 1,
                        ]
                    );

                    $gameConfiguration = GameConfiguration::query()->updateOrCreate(
                        ['lesson_id' => $lesson->id],
                        [
                            'title' => 'Juego '.$lessonBlueprint['title'],
                            'game_type_id' => $lessonBlueprint['game_type_id'],
                            'course_id' => $course->id,
                            'module_id' => $module->id,
                            'config' => [
                                'activity_type' => $lessonBlueprint['activity_type'],
                                'seed' => true,
                            ],
                            'max_score' => 100,
                            'time_limit' => max(5, (int) round(($lessonBlueprint['duration'] ?? 300) / 60)),
                            'max_attempts' => $lessonBlueprint['max_attempts'] ?? 3,
                            'is_active' => true,
                        ]
                    );

                    $quiz = null;

                    if (! empty($lessonBlueprint['quiz_questions'])) {
                        $quiz = Quiz::query()->updateOrCreate(
                            ['lesson_id' => $lesson->id],
                            [
                                'title' => $lessonBlueprint['quiz_title'] ?? $lessonBlueprint['title'],
                                'course_id' => $course->id,
                                'module_id' => $module->id,
                                'description' => $lessonBlueprint['quiz_description'] ?? null,
                                'passing_score' => $lessonBlueprint['passing_score'] ?? 70,
                                'time_limit' => max(5, (int) round(($lessonBlueprint['duration'] ?? 300) / 60)),
                                'max_attempts' => $lessonBlueprint['max_attempts'] ?? 3,
                                'is_active' => true,
                            ]
                        );

                        foreach ($lessonBlueprint['quiz_questions'] as $questionIndex => $question) {
                            Question::query()->updateOrCreate(
                                [
                                    'quiz_id' => $quiz->id,
                                    'sort_order' => $questionIndex + 1,
                                ],
                                [
                                    'question' => $question['prompt'],
                                    'type' => 'multiple_choice',
                                    'options' => $question['options'],
                                    'correct_answer' => collect($question['options'])->firstWhere('is_correct', true)['id'] ?? null,
                                    'points' => $question['points'] ?? 10,
                                    'explanation' => $question['explanation'] ?? 'Respuesta esperada en el escenario QA.',
                                ]
                            );
                        }
                    }

                    $lesson->forceFill([
                        'contentable_type' => InteractiveConfig::class,
                        'contentable_id' => $interactiveConfig->id,
                        'game_config_id' => $gameConfiguration->id,
                        'quiz_id' => $quiz?->id,
                    ])->save();
                }

                $lessonRegistry[$lessonBlueprint['key'] ?? ($moduleIndex.'-'.$lessonIndex)] = $lesson->fresh([
                    'interactiveConfig',
                    'contentable',
                ]);
            }
        }

        return [
            'course' => $course,
            'lessons' => $lessonRegistry,
        ];
    }

    private function seedScenarioStudents()
    {
        $profiles = [
            [
                'name' => 'Maria Compras',
                'email' => 'maria.compras@plataforma.com',
                'avatar' => 'https://i.pravatar.cc/300?img=71',
                'bio' => 'Escenario QA de compra completada con curso sin iniciar.',
                'total_points' => 420,
                'total_coins' => 180,
                'current_streak' => 1,
                'last_active_at' => now()->subHours(6),
            ],
            [
                'name' => 'Tomas Inicio',
                'email' => 'tomas.inicio@plataforma.com',
                'avatar' => 'https://i.pravatar.cc/300?img=44',
                'bio' => 'Alumno con curso empezado y actividades fallidas para probar intentos.',
                'total_points' => 860,
                'total_coins' => 240,
                'current_streak' => 2,
                'last_active_at' => now()->subHours(4),
            ],
            [
                'name' => 'Gabriela Certificada',
                'email' => 'gabriela.certificada@plataforma.com',
                'avatar' => 'https://i.pravatar.cc/300?img=58',
                'bio' => 'Estudiante demo con curso terminado y certificado emitido.',
                'total_points' => 3520,
                'total_coins' => 540,
                'current_streak' => 11,
                'last_active_at' => now()->subHours(2),
            ],
            [
                'name' => 'Hector Examen',
                'email' => 'hector.examen@plataforma.com',
                'avatar' => 'https://i.pravatar.cc/300?img=33',
                'bio' => 'Escenario de examen final aprobado y certificado automatico.',
                'total_points' => 3980,
                'total_coins' => 620,
                'current_streak' => 13,
                'last_active_at' => now()->subHour(),
            ],
            [
                'name' => 'Nuria Cupones',
                'email' => 'nuria.cupones@plataforma.com',
                'avatar' => 'https://i.pravatar.cc/300?img=67',
                'bio' => 'Estudiante con cupones usados y sin usar para probar checkout.',
                'total_points' => 1240,
                'total_coins' => 980,
                'current_streak' => 3,
                'last_active_at' => now()->subHours(10),
            ],
            [
                'name' => 'Pablo Premium',
                'email' => 'pablo.premium@plataforma.com',
                'avatar' => 'https://i.pravatar.cc/300?img=74',
                'bio' => 'Perfil con muchos puntos, inventario amplio y compras de tienda.',
                'total_points' => 4860,
                'total_coins' => 1320,
                'current_streak' => 21,
                'last_active_at' => now()->subHours(5),
            ],
            [
                'name' => 'Lina Basica',
                'email' => 'lina.basica@plataforma.com',
                'avatar' => 'https://i.pravatar.cc/300?img=76',
                'bio' => 'Perfil con pocos puntos y pagos rechazados para QA.',
                'total_points' => 80,
                'total_coins' => 40,
                'current_streak' => 0,
                'last_active_at' => now()->subDay(),
            ],
        ];

        return collect($profiles)->map(function (array $profile) {
            return User::query()->updateOrCreate(
                ['email' => $profile['email']],
                array_merge($profile, [
                    'role' => 'student',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ])
            );
        })->keyBy('email');
    }

    private function seedUserProfiles($scenarioStudents): void
    {
        $users = collect([
            User::query()->where('email', 'estudiante@plataforma.com')->first(),
            ...$scenarioStudents->all(),
        ])->filter();

        foreach ($users as $user) {
            UserProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'headline' => match ($user->email) {
                        'gabriela.certificada@plataforma.com' => 'Estudiante con certificacion activa',
                        'hector.examen@plataforma.com' => 'Perfil QA de examen final',
                        'nuria.cupones@plataforma.com' => 'Especialista en checkout y cupones',
                        'pablo.premium@plataforma.com' => 'Coleccionista de recompensas y marcos',
                        'lina.basica@plataforma.com' => 'Nueva estudiante en exploracion',
                        default => 'Perfil demo del ecosistema LMS',
                    },
                    'mini_bio' => $user->bio,
                    'location' => 'La Paz, Bolivia',
                ]
            );
        }
    }

    private function seedTeacherPayouts($teachers, User $admin): void
    {
        $plans = [
            [
                'teacher' => 'profesor@plataforma.com',
                'key' => 'paid-payout-profesor',
                'gross_amount' => 820.00,
                'platform_fee_amount' => 205.00,
                'net_amount' => 615.00,
                'status' => 'paid',
                'requested_at' => now()->subDays(18),
                'reviewed_at' => now()->subDays(17),
                'paid_at' => now()->subDays(15),
                'admin_notes' => 'Retiro conciliado y transferido.',
                'has_open_disputes' => false,
            ],
            [
                'teacher' => 'anag@plataforma.com',
                'key' => 'approved-payout-anag',
                'gross_amount' => 560.00,
                'platform_fee_amount' => 140.00,
                'net_amount' => 420.00,
                'status' => 'approved',
                'requested_at' => now()->subDays(7),
                'reviewed_at' => now()->subDays(6),
                'paid_at' => null,
                'admin_notes' => 'Aprobado, pendiente de desembolso.',
                'has_open_disputes' => false,
            ],
            [
                'teacher' => 'diego.murillo@plataforma.com',
                'key' => 'pending-payout-diego',
                'gross_amount' => 930.00,
                'platform_fee_amount' => 232.50,
                'net_amount' => 697.50,
                'status' => 'pending',
                'requested_at' => now()->subDays(3),
                'reviewed_at' => null,
                'paid_at' => null,
                'admin_notes' => 'Pendiente de revision del admin.',
                'has_open_disputes' => false,
            ],
            [
                'teacher' => 'sara.molina@plataforma.com',
                'key' => 'rejected-payout-sara',
                'gross_amount' => 470.00,
                'platform_fee_amount' => 117.50,
                'net_amount' => 352.50,
                'status' => 'rejected',
                'requested_at' => now()->subDays(9),
                'reviewed_at' => now()->subDays(8),
                'paid_at' => null,
                'admin_notes' => 'Rechazado temporalmente por disputa abierta.',
                'has_open_disputes' => true,
                'dispute_notes' => 'Compra QR bajo revision por comprobante inconsistente.',
            ],
        ];

        foreach ($plans as $plan) {
            $teacher = $teachers->get($plan['teacher']);

            if (! $teacher) {
                continue;
            }

            Payout::query()->updateOrCreate(
                [
                    'instructor_id' => $teacher->id,
                    'requested_at' => $plan['requested_at'],
                ],
                [
                    'approved_by' => in_array($plan['status'], ['approved', 'rejected', 'paid'], true) ? $admin->id : null,
                    'gross_amount' => $plan['gross_amount'],
                    'platform_fee_amount' => $plan['platform_fee_amount'],
                    'net_amount' => $plan['net_amount'],
                    'currency' => 'BOB',
                    'status' => $plan['status'],
                    'has_open_disputes' => $plan['has_open_disputes'],
                    'dispute_notes' => $plan['dispute_notes'] ?? null,
                    'admin_notes' => $plan['admin_notes'] ?? null,
                    'reviewed_at' => $plan['reviewed_at'],
                    'paid_at' => $plan['paid_at'],
                    'metadata' => ['seed_key' => $plan['key']],
                ]
            );
        }
    }

    private function seedInventoryAndCoupons($scenarioStudents): void
    {
        $catalog = ShopItem::query()->whereIn('slug', [
            'marco-avatar-aurora',
            'marco-avatar-solar',
            'marco-avatar-obsidiana',
            'titulo-veterano',
            'titulo-arquitecto-qa',
            'titulo-comentador-activo',
            'cupon-10-marketplace',
            'cupon-20-premium',
        ])->get()->keyBy('slug');

        $plans = [
            'estudiante@plataforma.com' => [
                ['slug' => 'marco-avatar-aurora', 'seed_key' => 'demo-frame', 'equipped' => true],
                ['slug' => 'titulo-veterano', 'seed_key' => 'demo-title', 'equipped' => true],
                ['slug' => 'cupon-10-marketplace', 'seed_key' => 'demo-coupon-unused', 'code' => 'SAVE10-DEMO-01'],
            ],
            'gabriela.certificada@plataforma.com' => [
                ['slug' => 'marco-avatar-solar', 'seed_key' => 'gabi-frame', 'equipped' => true],
                ['slug' => 'titulo-arquitecto-qa', 'seed_key' => 'gabi-title', 'equipped' => true],
            ],
            'hector.examen@plataforma.com' => [
                ['slug' => 'marco-avatar-obsidiana', 'seed_key' => 'hector-frame', 'equipped' => true],
                ['slug' => 'titulo-arquitecto-qa', 'seed_key' => 'hector-title', 'equipped' => true],
            ],
            'nuria.cupones@plataforma.com' => [
                ['slug' => 'marco-avatar-aurora', 'seed_key' => 'nuria-frame', 'equipped' => true],
                ['slug' => 'titulo-comentador-activo', 'seed_key' => 'nuria-title', 'equipped' => true],
                ['slug' => 'cupon-10-marketplace', 'seed_key' => 'nuria-coupon-unused', 'code' => 'SAVE10-NURIA'],
                ['slug' => 'cupon-20-premium', 'seed_key' => 'nuria-coupon-used', 'code' => 'SAVE20-NURIA'],
            ],
            'pablo.premium@plataforma.com' => [
                ['slug' => 'marco-avatar-obsidiana', 'seed_key' => 'pablo-frame', 'equipped' => true],
                ['slug' => 'titulo-veterano', 'seed_key' => 'pablo-title', 'equipped' => true],
                ['slug' => 'cupon-10-marketplace', 'seed_key' => 'pablo-coupon', 'code' => 'SAVE10-PABLO'],
            ],
        ];

        foreach ($plans as $email => $items) {
            $user = $email === 'estudiante@plataforma.com'
                ? User::query()->where('email', $email)->first()
                : $scenarioStudents->get($email);

            if (! $user) {
                continue;
            }

            foreach ($items as $offset => $itemPlan) {
                $shopItem = $catalog->get($itemPlan['slug']);

                if (! $shopItem) {
                    continue;
                }

                $this->grantInventoryItem(
                    $user,
                    $shopItem,
                    $itemPlan['seed_key'],
                    now()->subDays(10 - $offset),
                    (bool) ($itemPlan['equipped'] ?? false),
                    $itemPlan['code'] ?? null
                );
            }
        }
    }

    private function grantInventoryItem(
        User $user,
        ShopItem $shopItem,
        string $seedKey,
        Carbon $purchasedAt,
        bool $equipped = false,
        ?string $couponCode = null
    ): UserItem {
        $purchase = Purchase::query()
            ->where('user_id', $user->id)
            ->where('shop_item_id', $shopItem->id)
            ->where('metadata->seed_key', $seedKey)
            ->first();

        if (! $purchase) {
            $purchase = Purchase::query()->create([
                'user_id' => $user->id,
                'shop_item_id' => $shopItem->id,
                'cost_coins' => $shopItem->cost_coins,
                'status' => 'completed',
                'metadata' => ['seed_key' => $seedKey],
                'purchased_at' => $purchasedAt,
                'created_at' => $purchasedAt,
                'updated_at' => $purchasedAt,
            ]);
        }

        $itemMetadata = match ($shopItem->type) {
            'avatar_frame' => [
                'seed_key' => $seedKey,
                'frame_class' => $shopItem->metadata['frame_class'] ?? $shopItem->metadata['frame_style'] ?? 'frame-default',
                'accent_color' => $shopItem->metadata['accent_color'] ?? null,
            ],
            'profile_title' => [
                'seed_key' => $seedKey,
                'title' => $shopItem->metadata['title'] ?? $shopItem->name,
                'title_color' => $shopItem->metadata['title_color'] ?? null,
            ],
            'discount_coupon' => [
                'seed_key' => $seedKey,
                'coupon_code' => $couponCode,
                'discount_percent' => $shopItem->metadata['discount_percent'] ?? 0,
            ],
            default => ['seed_key' => $seedKey],
        };

        $userItem = UserItem::query()->updateOrCreate(
            ['purchase_id' => $purchase->id],
            [
                'user_id' => $user->id,
                'shop_item_id' => $shopItem->id,
                'item_type' => $shopItem->type,
                'is_equipped' => $equipped,
                'is_used' => false,
                'metadata' => $itemMetadata,
                'acquired_at' => $purchasedAt,
                'used_at' => null,
            ]
        );

        if ($shopItem->type === 'discount_coupon') {
            UserCoupon::query()->updateOrCreate(
                ['user_item_id' => $userItem->id],
                [
                    'user_id' => $user->id,
                    'shop_item_id' => $shopItem->id,
                    'code' => strtoupper($couponCode ?? ('SAVE-'.Str::upper(Str::random(8)))),
                    'discount_percent' => $shopItem->metadata['discount_percent'] ?? 0,
                    'is_used' => false,
                    'used_at' => null,
                    'payment_id' => null,
                    'metadata' => ['seed_key' => $seedKey],
                ]
            );
        }

        if ($equipped && in_array($shopItem->type, ['avatar_frame', 'profile_title'], true)) {
            UserItem::query()
                ->where('user_id', $user->id)
                ->where('item_type', $shopItem->type)
                ->where('id', '!=', $userItem->id)
                ->update(['is_equipped' => false]);

            $profile = UserProfile::query()->firstOrCreate(['user_id' => $user->id]);
            if ($shopItem->type === 'avatar_frame') {
                $profile->equipped_avatar_frame_item_id = $userItem->id;
            }
            if ($shopItem->type === 'profile_title') {
                $profile->equipped_profile_title_item_id = $userItem->id;
            }
            $profile->save();
        }

        return $userItem->fresh(['coupon']);
    }

    private function seedStudentJourneys($scenarioStudents, array $scenarioCourses, User $admin): void
    {
        $template = CertificateTemplate::query()->where('is_default', true)->first()
            ?? CertificateTemplate::query()->first();

        $this->applyJourneyScenario(
            $scenarioStudents->get('maria.compras@plataforma.com'),
            $scenarioCourses['no_cert_course'],
            [
                'payment_status' => 'completed',
                'journey' => 'not_started',
                'transaction_id' => 'SEED-MARIA-NO-START',
                'enrolled_at' => now()->subDays(4),
            ],
            $template,
            $admin
        );

        $this->applyJourneyScenario(
            $scenarioStudents->get('tomas.inicio@plataforma.com'),
            $scenarioCourses['exam_course'],
            [
                'payment_status' => 'completed',
                'journey' => 'in_progress',
                'transaction_id' => 'SEED-TOMAS-IN-PROGRESS',
                'enrolled_at' => now()->subDays(7),
            ],
            $template,
            $admin
        );

        $this->applyJourneyScenario(
            $scenarioStudents->get('gabriela.certificada@plataforma.com'),
            $scenarioCourses['nutrition_course'],
            [
                'payment_status' => 'completed',
                'journey' => 'completed_with_certificate',
                'transaction_id' => 'SEED-GABRIELA-CERT',
                'enrolled_at' => now()->subDays(28),
            ],
            $template,
            $admin
        );

        $this->applyJourneyScenario(
            $scenarioStudents->get('hector.examen@plataforma.com'),
            $scenarioCourses['exam_course'],
            [
                'payment_status' => 'completed',
                'journey' => 'completed_with_certificate',
                'transaction_id' => 'SEED-HECTOR-EXAM-CERT',
                'enrolled_at' => now()->subDays(21),
            ],
            $template,
            $admin
        );

        $this->applyJourneyScenario(
            $scenarioStudents->get('nuria.cupones@plataforma.com'),
            $scenarioCourses['fullstack_course'],
            [
                'payment_status' => 'pending',
                'journey' => 'not_started',
                'transaction_id' => 'SEED-NURIA-PENDING-QR',
                'enrolled_at' => now()->subDays(2),
                'create_enrollment' => false,
            ],
            $template,
            $admin
        );

        $this->applyJourneyScenario(
            $scenarioStudents->get('nuria.cupones@plataforma.com'),
            $scenarioCourses['ops_course'],
            [
                'payment_status' => 'completed',
                'journey' => 'not_started',
                'transaction_id' => 'SEED-NURIA-BOUGHT-OPS',
                'enrolled_at' => now()->subDays(9),
                'used_coupon_code' => 'SAVE20-NURIA',
                'coupon_owner_email' => 'nuria.cupones@plataforma.com',
            ],
            $template,
            $admin
        );

        $this->applyJourneyScenario(
            $scenarioStudents->get('pablo.premium@plataforma.com'),
            $scenarioCourses['no_cert_course'],
            [
                'payment_status' => 'completed',
                'journey' => 'completed_without_certificate',
                'transaction_id' => 'SEED-PABLO-NO-CERT',
                'enrolled_at' => now()->subDays(13),
            ],
            $template,
            $admin
        );

        $this->applyJourneyScenario(
            $scenarioStudents->get('lina.basica@plataforma.com'),
            $scenarioCourses['pending_course'],
            [
                'payment_status' => 'failed',
                'journey' => 'not_started',
                'transaction_id' => 'SEED-LINA-REJECTED',
                'enrolled_at' => now()->subDays(1),
                'create_enrollment' => false,
            ],
            $template,
            $admin
        );
    }

    private function applyJourneyScenario(
        ?User $user,
        ?Course $course,
        array $plan,
        ?CertificateTemplate $template,
        User $admin
    ): void {
        if (! $user || ! $course) {
            return;
        }

        $course->loadMissing(['modules.lessons.interactiveConfig']);

        $enrolledAt = Carbon::parse($plan['enrolled_at']);
        $createEnrollment = $plan['create_enrollment'] ?? true;
        $coupon = null;

        if (! empty($plan['used_coupon_code']) && ! empty($plan['coupon_owner_email'])) {
            $couponOwner = User::query()->where('email', $plan['coupon_owner_email'])->first();
            if ($couponOwner) {
                $coupon = UserCoupon::query()
                    ->where('user_id', $couponOwner->id)
                    ->where('code', strtoupper($plan['used_coupon_code']))
                    ->first();
            }
        }

        $payment = $this->upsertPayment($user, $course, $plan['transaction_id'], $plan['payment_status'], $enrolledAt, $admin, $coupon);

        if (! $createEnrollment) {
            $this->clearJourneyState($user, $course);
            Enrollment::query()->where('user_id', $user->id)->where('course_id', $course->id)->delete();
            return;
        }

        $this->clearJourneyState($user, $course);

        $lessons = $course->modules
            ->sortBy('sort_order')
            ->flatMap(fn (Module $module) => $module->lessons->sortBy('sort_order'))
            ->values();

        $progressPercent = 0.0;
        $cursor = $enrolledAt->copy()->addHours(3);

        if ($plan['journey'] === 'in_progress') {
            $completedContent = 0;
            $totalTrackable = $lessons->count();

            foreach ($lessons as $lesson) {
                if (in_array($lesson->type, ['video', 'reading', 'resource'], true) && $completedContent < 3) {
                    $this->completeStandardLesson($user, $course, $lesson, $cursor);
                    $completedContent++;
                    $cursor->addHours(8);
                    continue;
                }

                if ($lesson->type === 'interactive') {
                    $this->recordAttemptSeries($user, $course, $lesson, $cursor, [
                        ['score' => 40, 'passed' => false, 'locked' => false],
                        ['score' => 55, 'passed' => false, 'locked' => false],
                    ]);
                    break;
                }
            }

            $progressPercent = round(($completedContent / max(1, $totalTrackable)) * 100, 2);
        }

        if ($plan['journey'] === 'completed_with_certificate' || $plan['journey'] === 'completed_without_certificate') {
            foreach ($lessons as $lesson) {
                if (in_array($lesson->type, ['video', 'reading', 'resource'], true)) {
                    $this->completeStandardLesson($user, $course, $lesson, $cursor);
                } elseif ($lesson->type === 'interactive') {
                    $this->recordAttemptSeries($user, $course, $lesson, $cursor, [
                        ['score' => 88, 'passed' => true, 'locked' => false],
                    ]);
                }

                $cursor->addHours(6);
            }

            $progressPercent = 100.0;

            if ($plan['journey'] === 'completed_with_certificate' && $course->has_certificate) {
                $this->upsertCertificate($user, $course, $template, $cursor->copy()->subHours(2), 'automatic');
            }
        }

        Enrollment::query()->updateOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            [
                'progress' => $progressPercent,
                'enrolled_at' => $enrolledAt,
            ]
        );

        if ($payment->status === 'completed') {
            AdminActivityLog::record($admin, 'demo_payment_completed', $payment, [
                'user_email' => $user->email,
                'course_slug' => $course->slug,
            ]);
        }
    }

    private function upsertPayment(
        User $user,
        Course $course,
        string $transactionId,
        string $status,
        Carbon $createdAt,
        User $admin,
        ?UserCoupon $coupon = null
    ): Payment {
        $originalAmount = (float) $course->price;
        $discountPercent = $coupon ? (float) $coupon->discount_percent : 0;
        $discountAmount = round($originalAmount * ($discountPercent / 100), 2);
        $finalAmount = max(0, round($originalAmount - $discountAmount, 2));
        $platformFee = round($finalAmount * 0.25, 2);
        $instructorAmount = round($finalAmount - $platformFee, 2);

        $payment = Payment::query()->updateOrCreate(
            ['transaction_id' => $transactionId],
            [
                'user_id' => $user->id,
                'course_id' => $course->id,
                'amount' => $finalAmount,
                'original_amount' => $originalAmount,
                'status' => $status,
                'payment_method' => $finalAmount <= 0 ? 'coupon' : 'qr_manual',
                'provider' => $finalAmount <= 0 ? 'internal_coupon' : 'bolivia_qr',
                'qr_data' => json_encode([
                    'provider' => 'qr-local',
                    'reference' => $transactionId,
                    'bank' => 'Banco Demo Bolivia',
                    'expires_at' => $createdAt->copy()->addMinutes(35)->toIso8601String(),
                ], JSON_UNESCAPED_UNICODE),
                'coupon_code' => $coupon?->code,
                'coupon_discount_percent' => $discountPercent,
                'coupon_discount_amount' => $discountAmount,
                'user_coupon_id' => $coupon?->id,
                'reviewed_by' => in_array($status, ['completed', 'failed'], true) ? $admin->id : null,
                'reviewed_at' => in_array($status, ['completed', 'failed'], true) ? $createdAt->copy()->addHours(4) : null,
                'review_notes' => match ($status) {
                    'completed' => 'Pago conciliado manualmente por QR.',
                    'failed' => 'Comprobante QR rechazado por inconsistencia visual.',
                    default => 'Pendiente de revision.',
                },
                'platform_fee_amount' => $platformFee,
                'instructor_amount' => $instructorAmount,
            ]
        );

        if ($coupon) {
            $usedAt = $status === 'completed' ? $createdAt->copy()->addHours(1) : null;

            $coupon->forceFill([
                'payment_id' => $status === 'completed' ? $payment->id : null,
                'is_used' => $status === 'completed',
                'used_at' => $usedAt,
            ])->save();

            $coupon->userItem?->forceFill([
                'is_used' => $status === 'completed',
                'used_at' => $usedAt,
            ])->save();
        }

        return $payment;
    }

    private function clearJourneyState(User $user, Course $course): void
    {
        UserLessonProgress::query()->where('user_id', $user->id)->where('course_id', $course->id)->delete();
        InteractiveActivityResult::query()->where('user_id', $user->id)->where('course_id', $course->id)->delete();
        ActivityLog::query()->where('user_id', $user->id)->where('course_id', $course->id)->delete();
        ActivityAttempt::query()->where('user_id', $user->id)->where('course_id', $course->id)->delete();
        Certificate::query()->where('user_id', $user->id)->where('course_id', $course->id)->delete();
    }

    private function completeStandardLesson(User $user, Course $course, Lesson $lesson, Carbon $completedAt): void
    {
        UserLessonProgress::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'course_id' => $course->id,
                'lesson_id' => $lesson->id,
            ],
            [
                'module_id' => $lesson->module_id,
                'started_at' => $completedAt->copy()->subMinutes(18),
                'completed_at' => $completedAt,
                'time_spent_seconds' => max(120, min($lesson->duration ?? 600, 900)),
                'is_completed' => true,
            ]
        );
    }

    private function recordAttemptSeries(User $user, Course $course, Lesson $lesson, Carbon $baseTime, array $attempts): void
    {
        $interactiveConfig = $lesson->interactiveConfig;

        if (! $interactiveConfig) {
            return;
        }

        foreach ($attempts as $index => $attemptPlan) {
            $attemptNumber = $index + 1;
            $attemptedAt = $baseTime->copy()->addMinutes($attemptNumber * 18);
            $score = (float) $attemptPlan['score'];
            $passed = (bool) $attemptPlan['passed'];
            $locked = (bool) $attemptPlan['locked'];
            $multiplier = $interactiveConfig->rewardMultiplierForAttempt($attemptNumber);
            $xpAwarded = $passed ? (int) round($interactiveConfig->xp_reward * $multiplier) : 0;
            $xpPenalty = $passed ? 0 : (int) round($interactiveConfig->xp_reward * (1 - $multiplier));
            $coinAwarded = $passed ? (int) round($interactiveConfig->coin_reward * $multiplier) : 0;

            $attempt = ActivityAttempt::query()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'module_id' => $lesson->module_id,
                'lesson_id' => $lesson->id,
                'interactive_config_id' => $interactiveConfig->id,
                'attempt_number' => $attemptNumber,
                'score' => $score,
                'max_score' => 100,
                'score_percentage' => $score,
                'passing_score' => $interactiveConfig->passing_score,
                'xp_awarded' => $xpAwarded,
                'xp_penalty' => $xpPenalty,
                'coin_awarded' => $coinAwarded,
                'passed' => $passed,
                'locked' => $locked,
                'payload' => [
                    'seeded' => true,
                    'activity_type' => $interactiveConfig->activity_type,
                ],
                'attempted_at' => $attemptedAt,
                'created_at' => $attemptedAt,
                'updated_at' => $attemptedAt,
            ]);

            ActivityLog::query()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'module_id' => $lesson->module_id,
                'lesson_id' => $lesson->id,
                'interactive_config_id' => $interactiveConfig->id,
                'attempt_number' => $attemptNumber,
                'score' => (int) round($score),
                'passing_score' => $interactiveConfig->passing_score,
                'xp_awarded' => $xpAwarded,
                'coin_awarded' => $coinAwarded,
                'reward_multiplier' => $multiplier,
                'status' => $passed ? 'passed' : 'failed',
                'payload' => [
                    'seeded' => true,
                    'attempt_id' => $attempt->id,
                ],
                'attempted_at' => $attemptedAt,
                'created_at' => $attemptedAt,
                'updated_at' => $attemptedAt,
            ]);

            InteractiveActivityResult::query()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'module_id' => $lesson->module_id,
                'lesson_id' => $lesson->id,
                'interactive_config_id' => $interactiveConfig->id,
                'source_type' => 'interactive_renderer',
                'source_id' => $attempt->id,
                'score' => $score,
                'max_score' => 100,
                'attempts_used' => $attemptNumber,
                'xp_awarded' => $xpAwarded,
                'coin_awarded' => $coinAwarded,
                'badges_awarded' => [],
                'status' => $passed ? 'completed' : 'failed',
                'is_locked' => $locked,
                'requires_teacher_reset' => $locked,
                'completed_at' => $attemptedAt,
                'last_attempt_at' => $attemptedAt,
                'created_at' => $attemptedAt,
                'updated_at' => $attemptedAt,
            ]);
        }
    }

    private function upsertCertificate(
        User $user,
        Course $course,
        ?CertificateTemplate $template,
        Carbon $issuedAt,
        string $issuedVia
    ): void {
        Certificate::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'course_id' => $course->id,
            ],
            [
                'template_id' => $template?->id,
                'certificate_code' => 'CERT-'.Str::upper(Str::slug($user->email.'-'.$course->slug)),
                'student_name' => $user->name,
                'course_name' => $course->title,
                'final_score' => max((int) $course->certificate_min_score, 88),
                'issued_at' => $issuedAt,
                'expiry_date' => null,
                'download_url' => 'https://plataforma.test/certificados/'.Str::uuid(),
                'pdf_path' => 'certificates/'.Str::slug($user->email.'-'.$course->slug).'.pdf',
                'verification_url' => 'https://plataforma.test/certificados/verificar/'.Str::uuid(),
                'issued_via' => $issuedVia,
                'metadata' => [
                    'seeded' => true,
                    'qr_reference' => 'QR-CERT-'.Str::upper(Str::random(8)),
                ],
            ]
        );
    }

    private function seedCommentThreads($scenarioStudents, array $scenarioCourses): void
    {
        $threads = [
            [
                'student_email' => 'tomas.inicio@plataforma.com',
                'course_key' => 'exam_course',
                'lesson_key' => 'Lectura guiada del flujo DevOps',
                'body' => 'No me queda claro en que momento se bloquean los intentos. ¿Es por actividad o por curso?',
                'reply' => 'Se bloquean por actividad. Cuando agotaste el max_attempts de esa actividad, necesitas reset del docente.',
            ],
            [
                'student_email' => 'maria.compras@plataforma.com',
                'course_key' => 'no_cert_course',
                'lesson_key' => 'Video demo del curso sin certificado',
                'body' => 'Compré el curso, pero aun no empiezo. ¿Este video inicial ya resume el flujo completo?',
                'reply' => null,
            ],
            [
                'student_email' => 'nuria.cupones@plataforma.com',
                'course_key' => 'fullstack_course',
                'lesson_key' => null,
                'body' => 'Apliqué un cupón y quiero revisar el temario antes de terminar la compra. ¿Cuál lección recomiendan primero?',
                'reply' => 'Empieza por la sesión guiada del primer módulo; te ubica rápido en arquitectura y flujo de datos.',
            ],
        ];

        foreach ($threads as $thread) {
            $student = $thread['student_email'] === 'estudiante@plataforma.com'
                ? User::query()->where('email', $thread['student_email'])->first()
                : $scenarioStudents->get($thread['student_email']);
            $course = $scenarioCourses[$thread['course_key']] ?? null;

            if (! $student || ! $course) {
                continue;
            }

            $course->loadMissing(['modules.lessons.contentable', 'instructor']);
            $lesson = $thread['lesson_key']
                ? $course->modules->flatMap->lessons->first(function (Lesson $lesson) use ($thread) {
                    return Str::slug($lesson->title) === Str::slug(str_replace('_', ' ', $thread['lesson_key']))
                        || $lesson->title === $thread['lesson_key'];
                })
                : $course->modules->flatMap->lessons->first();

            $commentable = $lesson?->contentable;

            if (! $commentable) {
                continue;
            }

            $question = Comment::query()->updateOrCreate(
                [
                    'user_id' => $student->id,
                    'commentable_type' => $commentable->getMorphClass(),
                    'commentable_id' => $commentable->getKey(),
                    'body' => $thread['body'],
                ],
                [
                    'is_question' => true,
                    'resolved_at' => $thread['reply'] ? now()->subHours(12) : null,
                ]
            );

            if ($thread['reply']) {
                Comment::query()->updateOrCreate(
                    [
                        'parent_id' => $question->id,
                        'user_id' => $course->instructor_id,
                        'commentable_type' => $commentable->getMorphClass(),
                        'commentable_id' => $commentable->getKey(),
                    ],
                    [
                        'body' => $thread['reply'],
                        'is_question' => false,
                        'resolved_at' => null,
                    ]
                );
            }
        }
    }

    private function seedAdminLogs(User $admin, array $scenarioCourses, $teachers): void
    {
        foreach ([
            ['action' => 'course_published', 'target' => $scenarioCourses['exam_course']],
            ['action' => 'course_sent_back_to_draft', 'target' => $scenarioCourses['rejected_course']],
            ['action' => 'course_pending_review', 'target' => $scenarioCourses['pending_course']],
        ] as $entry) {
            if (! $entry['target']) {
                continue;
            }

            AdminActivityLog::record($admin, $entry['action'], $entry['target'], [
                'seeded' => true,
                'status' => $entry['target']->status,
            ]);
        }

        foreach ($teachers as $teacher) {
            AdminActivityLog::record($admin, 'payout_audit_snapshot', $teacher, [
                'seeded' => true,
                'payouts_count' => $teacher->payouts()->count(),
            ]);
        }
    }

    private function basicTriviaPayload(): array
    {
        return [
            'title' => 'Trivia de conceptos base',
            'description' => 'Responde las preguntas para validar comprension del tema.',
            'questions' => $this->basicTriviaQuestions(),
        ];
    }

    private function basicTriviaQuestions(): array
    {
        return [
            [
                'prompt' => '¿Qué capa suele encargarse de la logica y acceso a datos?',
                'options' => [
                    ['id' => 'a', 'text' => 'Backend', 'is_correct' => true],
                    ['id' => 'b', 'text' => 'CSS', 'is_correct' => false],
                    ['id' => 'c', 'text' => 'Diseño grafico', 'is_correct' => false],
                    ['id' => 'd', 'text' => 'SEO', 'is_correct' => false],
                ],
                'points' => 10,
            ],
            [
                'prompt' => '¿Qué sigla describe una interfaz para comunicar sistemas?',
                'options' => [
                    ['id' => 'a', 'text' => 'DOM', 'is_correct' => false],
                    ['id' => 'b', 'text' => 'API', 'is_correct' => true],
                    ['id' => 'c', 'text' => 'SPA', 'is_correct' => false],
                    ['id' => 'd', 'text' => 'PWA', 'is_correct' => false],
                ],
                'points' => 10,
            ],
            [
                'prompt' => '¿Qué concepto representa un bloque reutilizable de interfaz?',
                'options' => [
                    ['id' => 'a', 'text' => 'Componente', 'is_correct' => true],
                    ['id' => 'b', 'text' => 'Servidor DNS', 'is_correct' => false],
                    ['id' => 'c', 'text' => 'Webhook', 'is_correct' => false],
                    ['id' => 'd', 'text' => 'Firewall', 'is_correct' => false],
                ],
                'points' => 10,
            ],
            [
                'prompt' => '¿Qué tipo de app puede instalarse y comportarse como nativa en web?',
                'options' => [
                    ['id' => 'a', 'text' => 'PWA', 'is_correct' => true],
                    ['id' => 'b', 'text' => 'PDF', 'is_correct' => false],
                    ['id' => 'c', 'text' => 'ZIP', 'is_correct' => false],
                    ['id' => 'd', 'text' => 'CSV', 'is_correct' => false],
                ],
                'points' => 10,
            ],
            [
                'prompt' => '¿Qué capa visualiza datos e interaccion del usuario?',
                'options' => [
                    ['id' => 'a', 'text' => 'Frontend', 'is_correct' => true],
                    ['id' => 'b', 'text' => 'Queue worker', 'is_correct' => false],
                    ['id' => 'c', 'text' => 'Kernel', 'is_correct' => false],
                    ['id' => 'd', 'text' => 'Seeder', 'is_correct' => false],
                ],
                'points' => 10,
            ],
        ];
    }

    private function finalExamPayload(): array
    {
        return [
            'title' => 'Examen final de certificacion',
            'description' => 'Resuelve correctamente esta evaluacion para habilitar tu certificado.',
            'questions' => $this->finalExamQuestions(),
        ];
    }

    private function finalExamQuestions(): array
    {
        return [
            [
                'prompt' => 'Si una actividad exige 80% y max_attempts = 2, ¿qué ocurre tras dos fallos?',
                'options' => [
                    ['id' => 'a', 'text' => 'Se bloquea hasta reset docente', 'is_correct' => true],
                    ['id' => 'b', 'text' => 'Se convierte en recurso', 'is_correct' => false],
                    ['id' => 'c', 'text' => 'Se publica automaticamente', 'is_correct' => false],
                    ['id' => 'd', 'text' => 'Se reinicia sola', 'is_correct' => false],
                ],
                'points' => 20,
            ],
            [
                'prompt' => '¿Qué evento marca automaticamente un video como completado?',
                'options' => [
                    ['id' => 'a', 'text' => 'ended del reproductor', 'is_correct' => true],
                    ['id' => 'b', 'text' => 'play del reproductor', 'is_correct' => false],
                    ['id' => 'c', 'text' => 'scroll del alumno', 'is_correct' => false],
                    ['id' => 'd', 'text' => 'descarga del recurso', 'is_correct' => false],
                ],
                'points' => 20,
            ],
            [
                'prompt' => '¿Quién puede publicar finalmente un curso en el workflow QA?',
                'options' => [
                    ['id' => 'a', 'text' => 'El administrador', 'is_correct' => true],
                    ['id' => 'b', 'text' => 'Cualquier estudiante', 'is_correct' => false],
                    ['id' => 'c', 'text' => 'Solo el docente', 'is_correct' => false],
                    ['id' => 'd', 'text' => 'El middleware', 'is_correct' => false],
                ],
                'points' => 20,
            ],
            [
                'prompt' => '¿Qué debe existir para que un cupon sea valido en checkout?',
                'options' => [
                    ['id' => 'a', 'text' => 'Debe pertenecer al usuario y no estar usado', 'is_correct' => true],
                    ['id' => 'b', 'text' => 'Debe venir del admin solamente', 'is_correct' => false],
                    ['id' => 'c', 'text' => 'Debe ser de cualquier estudiante', 'is_correct' => false],
                    ['id' => 'd', 'text' => 'Debe estar ya usado', 'is_correct' => false],
                ],
                'points' => 20,
            ],
            [
                'prompt' => '¿Qué combinacion representa un curso con certificado y examen final?',
                'options' => [
                    ['id' => 'a', 'text' => 'has_certificate = true y certificate_requires_final_exam = true', 'is_correct' => true],
                    ['id' => 'b', 'text' => 'status = draft y comments = true', 'is_correct' => false],
                    ['id' => 'c', 'text' => 'price = 0 y thumbnail = null', 'is_correct' => false],
                    ['id' => 'd', 'text' => 'coins > 0 y payout = approved', 'is_correct' => false],
                ],
                'points' => 20,
            ],
        ];
    }

    private function matchingPayload(): array
    {
        return [
            'title' => 'Relaciona conceptos y definiciones',
            'description' => 'Empareja cada concepto del pipeline con su definicion correcta.',
            'points_per_pair' => 12,
            'pairs' => [
                ['id' => 1, 'left' => 'Observabilidad', 'right' => 'Conjunto de senales para entender el estado del sistema'],
                ['id' => 2, 'left' => 'Deploy', 'right' => 'Entrega del cambio a un entorno disponible'],
                ['id' => 3, 'left' => 'Rollback', 'right' => 'Volver a una version estable anterior'],
                ['id' => 4, 'left' => 'Pipeline', 'right' => 'Secuencia automatizada de validaciones y entrega'],
                ['id' => 5, 'left' => 'Incidente', 'right' => 'Evento que afecta disponibilidad o calidad del servicio'],
            ],
        ];
    }

    private function crosswordPayload(): array
    {
        return [
            'title' => 'Crucigrama de conceptos base',
            'description' => 'Completa el tablero usando pistas horizontales y verticales.',
            'rows' => 5,
            'cols' => 6,
            'points_per_word' => 10,
            'entries' => [
                ['id' => 1, 'row' => 0, 'col' => 0, 'direction' => 'across', 'clue' => 'Interfaz para comunicar sistemas', 'answer' => 'API'],
                ['id' => 2, 'row' => 0, 'col' => 0, 'direction' => 'down', 'clue' => 'Aplicacion instalada en un dispositivo', 'answer' => 'APP'],
                ['id' => 3, 'row' => 0, 'col' => 2, 'direction' => 'down', 'clue' => 'Sigla corta de inteligencia artificial', 'answer' => 'IA'],
                ['id' => 4, 'row' => 2, 'col' => 3, 'direction' => 'across', 'clue' => 'Modelo de objetos del documento', 'answer' => 'DOM'],
                ['id' => 5, 'row' => 2, 'col' => 5, 'direction' => 'down', 'clue' => 'Funcion que asocia un valor a otro', 'answer' => 'MAP'],
                ['id' => 6, 'row' => 4, 'col' => 0, 'direction' => 'across', 'clue' => 'Aplicacion web progresiva', 'answer' => 'PWA'],
            ],
        ];
    }
}
