<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    /**
     * Public catalog: list published courses with filters.
     */
    public function catalog(Request $request)
    {
        $query = Course::published()
            ->with(['instructor:id,name,avatar', 'category:id,name,slug'])
            ->withCount('enrollments as total_students');

        // Filter by category
        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->category));
        }

        // Filter by level
        if ($request->filled('level')) {
            $query->where('level', $request->level);
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

        return $query->paginate($request->get('per_page', 12));
    }

    /**
     * Show a single course with full details.
     */
    public function show(string $slug)
    {
        $course = Course::where('slug', $slug)
            ->published()
            ->with([
                'instructor:id,name,avatar,bio',
                'category:id,name,slug',
                'modules.lessons:id,module_id,title,type,duration,sort_order,is_free',
            ])
            ->withCount('enrollments as total_students')
            ->firstOrFail();

        return response()->json($course);
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
            'category_id' => 'nullable|exists:categories,id',
            'level' => 'required|in:beginner,intermediate,advanced,all_levels',
            'language' => 'nullable|string|max:10',
            'requirements' => 'nullable|array',
            'what_you_learn' => 'nullable|array',
        ]);

        $validated['slug'] = Str::slug($validated['title']).'-'.Str::random(5);
        $validated['instructor_id'] = $request->user()->id;
        $validated['status'] = 'draft';

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
            'category_id' => 'nullable|exists:categories,id',
            'level' => 'sometimes|in:beginner,intermediate,advanced,all_levels',
            'language' => 'nullable|string|max:10',
            'status' => 'sometimes|in:draft,pending,published,archived',
            'requirements' => 'nullable|array',
            'what_you_learn' => 'nullable|array',
        ]);

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
}
