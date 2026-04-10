<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $instructors = User::where('role', 'instructor')->get();
        $categories = Category::whereNull('parent_id')->get();
        $subcategories = Category::whereNotNull('parent_id')->get();

        $courses = [
            [
                'title' => 'Laravel Profesional: De Cero a Experto',
                'slug' => 'laravel-profesional-de-cero-a-experto',
                'description' => 'Aprende Laravel desde los fundamentos hasta técnicas avanzadas como colas, eventos, microservicios y deploy en producción. Este curso incluye proyectos reales y buenas prácticas de la industria.',
                'short_description' => 'Domina Laravel con proyectos reales y buenas prácticas.',
                'price' => 49.99,
                'thumbnail' => 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                'promo_video' => 'https://player.vimeo.com/video/76979871',
                'instructor_id' => $instructors->where('email', 'profesor@plataforma.com')->first()->id,
                'category_id' => $categories->where('slug', 'desarrollo')->first()->id,
                'level' => 'all_levels',
                'language' => 'es',
                'status' => 'published',
                'requirements' => ['Conocimientos básicos de PHP', 'Un editor de código (VS Code recomendado)'],
                'what_you_learn' => ['Crear APIs REST profesionales', 'Manejar autenticación con Sanctum', 'Desplegar en producción', 'Trabajar con colas y jobs'],
                'has_certificate' => true,
                'certificate_min_score' => 75,
            ],
            [
                'title' => 'Vue.js 3 + Quasar Framework: Interfaces Premium',
                'slug' => 'vuejs-3-quasar-framework-interfaces-premium',
                'description' => 'Construye aplicaciones web modernas y responsivas con Vue 3 Composition API y Quasar Framework. Aprende a crear componentes reutilizables y aplicaciones empresariales.',
                'short_description' => 'Interfaces profesionales con Vue 3 y Quasar.',
                'price' => 39.99,
                'thumbnail' => 'https://images.unsplash.com/photo-1633356122544-f134324a6cee?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                'promo_video' => 'https://player.vimeo.com/video/76979871',
                'instructor_id' => $instructors->where('email', 'profesor@plataforma.com')->first()->id,
                'category_id' => $subcategories->where('slug', 'frontend')->first()->id,
                'level' => 'intermediate',
                'language' => 'es',
                'status' => 'published',
                'requirements' => ['HTML, CSS y JavaScript básico', 'Conocimientos de Vue.js previo (deseable)'],
                'what_you_learn' => ['Composition API a fondo', 'State management con Pinia', 'Diseño responsivo premium', 'Despliegue con Quasar CLI'],
                'has_certificate' => true,
                'certificate_min_score' => 80,
            ],
            [
                'title' => 'Diseño UX/UI con Figma: Principios y Práctica',
                'slug' => 'diseno-ux-ui-con-figma',
                'description' => 'Aprende los principios de diseño UX/UI y domina Figma para crear prototipos interactivos. Desde wireframes hasta sistemas de diseño completos.',
                'short_description' => 'Desde wireframes hasta prototipos de alta fidelidad.',
                'price' => 29.99,
                'thumbnail' => 'https://images.unsplash.com/photo-1561070791-2526d30994b5?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                'promo_video' => 'https://player.vimeo.com/video/76979871',
                'instructor_id' => $instructors->where('email', 'anag@plataforma.com')->first()->id,
                'category_id' => $subcategories->where('slug', 'ui-design')->first()->id,
                'level' => 'beginner',
                'language' => 'es',
                'status' => 'published',
                'requirements' => ['Ninguno, solo ganas de aprender'],
                'what_you_learn' => ['Principios de UX', 'Prototipado en Figma', 'Design Systems', 'Pruebas de usabilidad'],
                'has_certificate' => false,
                'certificate_min_score' => 70,
            ],
            [
                'title' => 'Machine Learning con Python: Fundamentos',
                'slug' => 'machine-learning-con-python-fundamentos',
                'description' => 'Introducción al machine learning utilizando Python, scikit-learn y pandas. Proyectos prácticos con datasets reales.',
                'short_description' => 'Primeros pasos en machine learning con Python.',
                'price' => 59.99,
                'thumbnail' => 'https://images.unsplash.com/photo-1555255707-c07966088b7b?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                'promo_video' => 'https://player.vimeo.com/video/76979871',
                'instructor_id' => $instructors->where('email', 'profesor@plataforma.com')->first()->id,
                'category_id' => $categories->where('slug', 'data-science')->first()->id,
                'level' => 'intermediate',
                'language' => 'es',
                'status' => 'published',
                'requirements' => ['Python básico', 'Conocimientos de álgebra lineal'],
                'what_you_learn' => ['Preprocesamiento de datos', 'Modelos de clasificación', 'Validación cruzada', 'Reducción de dimensionalidad'],
                'has_certificate' => true,
                'certificate_min_score' => 85,
            ],
        ];

        foreach ($courses as $course) {
            Course::firstOrCreate(['slug' => $course['slug']], $course);
        }

        // Generar cursos adicionales con Faker
        Course::factory()->count(6)->create([
            'status' => 'published',
            'has_certificate' => fake()->boolean(70),
            'certificate_min_score' => fake()->numberBetween(70, 90),
        ]);
    }
}
