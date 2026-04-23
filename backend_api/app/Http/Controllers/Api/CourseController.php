<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\CourseWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    public function __construct(
        private readonly CourseWorkflowService $courseWorkflowService
    ) {
    }

    /**
     * Public catalog: list published courses with filters.
     */
    public function catalog(Request $request)
    {
        $user = auth('sanctum')->user();

        $query = Course::published()
            ->with(['instructor:id,name,avatar', 'category:id,name,slug'])
            ->withCount('enrollments as total_students')
            ->withExists([
                'lessons as has_interactive_activities' => fn ($q) => $q->whereIn('type', ['interactive', 'game', 'quiz']),
            ]);

        if ($user) {
            $query->withExists([
                'enrollments as is_enrolled' => fn ($q) => $q->where('user_id', $user->id),
            ]);
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->category));
        }

        // Filter by level
        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        // Filter by price type
        if ($request->filled('price')) {
            if ($request->price === 'free') {
                $query->where('price', '<=', 0);
            } elseif ($request->price === 'paid') {
                $query->where('price', '>', 0);
            }
        }

        // Filter by gamification availability
        if ($request->filled('gamification')) {
            $wantsGamification = filter_var($request->gamification, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($wantsGamification === true) {
                $query->whereHas('lessons', fn ($q) => $q->whereIn('type', ['interactive', 'game', 'quiz']));
            } elseif ($wantsGamification === false) {
                $query->whereDoesntHave('lessons', fn ($q) => $q->whereIn('type', ['interactive', 'game', 'quiz']));
            }
        }

        // Search by keyword
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortMap = [
            'newest' => ['created_at', 'desc'],
            'popular' => ['total_students', 'desc'],
            'price_low' => ['price', 'asc'],
            'price_high' => ['price', 'desc'],
        ];

        if ($request->filled('sort') && isset($sortMap[$request->sort])) {
            [$col, $dir] = $sortMap[$request->sort];
            if ($col === 'total_students') {
                $query->orderByDesc('total_students');
            } else {
                $query->orderBy($col, $dir);
            }
        } else {
            $query->latest();
        }

        $courses = $query->paginate($request->get('per_page', 12));

        if ($user) {
            $courses->getCollection()->transform(function (Course $course) use ($user) {
                $course->setAttribute('required_level', (int) $course->minimum_level_required);
                $course->setAttribute('user_level', (int) $user->current_level);
                $course->setAttribute('is_level_locked', (int) $user->current_level < (int) $course->minimum_level_required);

                return $course;
            });
        }

        return $courses;
    }

    /**
     * Show a single course with full details.
     */
    public function show(Request $request, string $slug)
    {
        $user = auth('sanctum')->user();
        $previewMode = $request->boolean('preview');

        $query = Course::where('slug', $slug)
            ->with([
                'instructor:id,name,avatar,bio',
                'category:id,name,slug',
                'modules.lessons:id,module_id,title,type,duration,sort_order,is_free,content_url,content_text',
                'certificateFinalLesson:id,module_id,title,type',
                'certificateFinalLesson.module:id,title',
                'shopItems:id,course_id,lesson_id,name,slug,type,cost_coins,minimum_level_required,metadata,is_active',
            ])
            ->withCount('enrollments as total_students');

        if (! $previewMode) {
            $query->published();
        }

        $course = $query->firstOrFail();

        if ($previewMode && (! $user || (! $user->isAdmin() && (int) $course->instructor_id !== (int) $user->id))) {
            abort(403, 'No tienes permiso para previsualizar este curso.');
        }

        $isEnrolled = false;
        $canManageCourse = false;

        if ($user) {
            $isEnrolled = $course->enrollments()
                ->where('user_id', $user->id)
                ->exists();

            $canManageCourse = $user->isAdmin() || (int) $course->instructor_id === (int) $user->id;
        }

        $hasInteractiveActivities = $course->lessons()
            ->whereIn('type', ['interactive', 'game', 'quiz'])
            ->exists();

        $course->setAttribute('is_enrolled', $isEnrolled);
        $course->setAttribute('can_manage_course', $canManageCourse);
        $course->setAttribute('is_preview', $previewMode);
        $course->setAttribute('required_level', (int) $course->minimum_level_required);
        $course->setAttribute('user_level', $user ? (int) $user->current_level : null);
        $course->setAttribute('is_level_locked', $user ? ((int) $user->current_level < (int) $course->minimum_level_required) : ((int) $course->minimum_level_required > 1));
        $course->setAttribute('has_interactive_activities', $hasInteractiveActivities);
        $course->setAttribute('player_tabs', $hasInteractiveActivities
            ? ['content', 'achievements_ranking']
            : ['content']);
        $course->setAttribute('preview_video_url', $course->promo_video);
        $course->setAttribute('shop_preview', $course->shopItems
            ->where('is_active', true)
            ->values()
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'type' => $item->type,
                'cost_coins' => $item->cost_coins,
                'minimum_level_required' => $item->minimum_level_required,
                'lesson_id' => $item->lesson_id,
            ])
            ->all());

        $course->modules->each(function ($module) {
            $module->lessons->each(function ($lesson) {
                $lesson->setAttribute('normalized_type', match ($lesson->type) {
                    'text' => 'reading',
                    'game', 'quiz' => 'interactive',
                    default => $lesson->type,
                });
            });
        });

        if (! $course->preview_video_url) {
            $previewLesson = $course->modules
                ->flatMap(fn ($module) => $module->lessons)
                ->first(fn ($lesson) => in_array($lesson->normalized_type, ['video', 'interactive'], true) || $lesson->is_free);

            $course->setAttribute('preview_video_url', $previewLesson?->content_url);
            $course->setAttribute('preview_lesson', $previewLesson ? [
                'id' => $previewLesson->id,
                'title' => $previewLesson->title,
                'type' => $previewLesson->normalized_type,
                'is_free' => (bool) $previewLesson->is_free,
            ] : null);
        } else {
            $course->setAttribute('preview_lesson', null);
        }

        return response()->json($course);
    }

    /**
     * List courses created by the authenticated instructor/admin.
     */
    public function mine(Request $request)
    {
        $user = $request->user();

        if (! $user->isInstructor() && ! $user->isAdmin()) {
            return response()->json([
                'message' => 'Solo instructores o administradores pueden acceder a este recurso.',
            ], 403);
        }

        $query = Course::query()
            ->with(['category:id,name,slug'])
            ->withCount([
                'enrollments as total_students',
                'modules',
                'lessons',
                'enrollments as active_students' => fn ($query) => $query->whereHas(
                    'user',
                    fn ($userQuery) => $userQuery
                        ->whereNotNull('last_active_at')
                        ->where('last_active_at', '>=', now()->subDays(7))
                ),
            ])
            ->withSum([
                'payments as completed_sales_amount' => fn ($query) => $query->where('status', 'completed'),
            ], 'amount')
            ->withAvg('interactiveActivityResults as average_learning_score', 'score');

        if (! $user->isAdmin() || ! $request->boolean('all')) {
            $query->where('instructor_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $query->latest()->paginate($request->get('per_page', 12));
    }

    /**
     * Update only the publication status for a course.
     */
    public function updateStatus(Request $request, Course $course)
    {
        $this->authorizeOwnerOrAdmin($request, $course);

        $user = $request->user();
        $allowed = $user->isAdmin()
            ? 'required|in:draft,pending,published'
            : 'required|in:draft,pending';

        $validated = $request->validate([
            'status' => $allowed,
            'review_notes' => 'nullable|string|max:2000',
        ]);

        $course = $this->courseWorkflowService->transition(
            $course,
            $user,
            $validated['status'],
            $validated['review_notes'] ?? null
        );

        return response()->json([
            'message' => match ($validated['status']) {
                'pending' => 'Curso enviado a revisión correctamente.',
                'published' => 'Curso publicado correctamente.',
                default => 'Curso movido a borrador correctamente.',
            },
            'course' => $course,
        ]);
    }

    /**
     * Store a new course (instructor/admin).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:300',
            'price' => 'required|numeric|min:0',
            'thumbnail' => 'nullable|string|max:1000',
            'promo_video' => 'nullable|string|max:1000',
            'category_id' => 'nullable|exists:categories,id',
            'level' => 'required|in:beginner,intermediate,advanced,all_levels',
            'language' => 'nullable|string|max:10',
            'status' => 'sometimes|in:draft,pending,published,archived',
            'minimum_level_required' => 'nullable|integer|min:1|max:99',
            'requirements' => 'nullable|array',
            'what_you_learn' => 'nullable|array',
            'has_certificate' => 'sometimes|boolean',
            'certificate_requires_final_exam' => 'sometimes|boolean',
            'certificate_final_lesson_id' => 'nullable|integer|exists:lessons,id',
            'certificate_exam_scope' => 'sometimes|in:lesson,course',
            'certificate_min_score' => 'nullable|integer|min:0|max:100',
        ]);

        $validated['slug'] = Str::slug($validated['title']).'-'.Str::random(5);
        $validated['instructor_id'] = $request->user()->id;
        $validated['status'] = $validated['status'] ?? 'draft';
        $validated['minimum_level_required'] = max(1, (int) ($validated['minimum_level_required'] ?? 1));
        $validated['certificate_requires_final_exam'] = (bool) ($validated['certificate_requires_final_exam'] ?? false);
        $validated['certificate_exam_scope'] = $validated['certificate_exam_scope'] ?? 'lesson';
        $validated['certificate_final_lesson_id'] = null;

        $course = Course::create($validated);

        return response()->json($course, 201);
    }

    /**
     * Update a course (owner or admin).
     */
    public function update(Request $request, Course $course)
    {
        $this->authorizeOwnerOrAdmin($request, $course);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:300',
            'price' => 'sometimes|numeric|min:0',
            'thumbnail' => 'nullable|string|max:1000',
            'promo_video' => 'nullable|string|max:1000',
            'category_id' => 'nullable|exists:categories,id',
            'level' => 'sometimes|in:beginner,intermediate,advanced,all_levels',
            'language' => 'nullable|string|max:10',
            'status' => 'sometimes|in:draft,pending,published,archived',
            'minimum_level_required' => 'nullable|integer|min:1|max:99',
            'requirements' => 'nullable|array',
            'what_you_learn' => 'nullable|array',
            'has_certificate' => 'sometimes|boolean',
            'certificate_requires_final_exam' => 'sometimes|boolean',
            'certificate_final_lesson_id' => 'nullable|integer|exists:lessons,id',
            'certificate_exam_scope' => 'sometimes|in:lesson,course',
            'certificate_min_score' => 'nullable|integer|min:0|max:100',
        ]);

        if (array_key_exists('minimum_level_required', $validated)) {
            $validated['minimum_level_required'] = max(1, (int) ($validated['minimum_level_required'] ?? 1));
        }

        // When scope is 'course', clear the specific lesson reference
        $scope = $validated['certificate_exam_scope'] ?? $course->certificate_exam_scope ?? 'lesson';
        if ($scope === 'course') {
            $validated['certificate_final_lesson_id'] = null;
        } elseif (array_key_exists('certificate_final_lesson_id', $validated)) {
            $this->assertCertificateFinalLessonBelongsToCourse($course, $validated['certificate_final_lesson_id']);
        }

        if (array_key_exists('has_certificate', $validated) && ! $validated['has_certificate']) {
            $validated['certificate_requires_final_exam'] = false;
            $validated['certificate_final_lesson_id'] = null;
        }

        if (array_key_exists('certificate_requires_final_exam', $validated) && ! $validated['certificate_requires_final_exam']) {
            $validated['certificate_final_lesson_id'] = null;
            $validated['certificate_exam_scope'] = 'lesson';
        }

        $course->update($validated);

        return response()->json($course->fresh());
    }

    /**
     * Delete a course (soft delete).
     */
    public function destroy(Request $request, Course $course)
    {
        $this->authorizeOwnerOrAdmin($request, $course);

        $course->delete();

        return response()->json(['message' => 'Curso eliminado.']);
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function authorizeOwnerOrAdmin(Request $request, Course $course): void
    {
        $user = $request->user();
        if ($user->id !== $course->instructor_id && ! $user->isAdmin()) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }
    }

    private function assertCertificateFinalLessonBelongsToCourse(Course $course, ?int $lessonId): void
    {
        if (! $lessonId) {
            return;
        }

        $lesson = $course->lessons()->find($lessonId);

        if (! $lesson) {
            abort(422, 'La evaluación final debe pertenecer al mismo curso.');
        }

        if ($lesson->normalized_type !== 'interactive') {
            abort(422, 'La evaluación final debe ser una lección de tipo actividad.');
        }
    }
}
