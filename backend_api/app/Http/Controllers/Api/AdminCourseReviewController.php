<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\CourseWorkflowService;
use Illuminate\Http\Request;

class AdminCourseReviewController extends Controller
{
    public function __construct(
        private readonly CourseWorkflowService $courseWorkflowService
    ) {
    }

    public function inbox(Request $request)
    {
        $query = Course::query()
            ->with(['instructor:id,name,email', 'approver:id,name,email', 'category:id,name,slug'])
            ->withCount([
                'modules',
                'lessons',
                'enrollments as total_students',
            ])
            ->withSum([
                'payments as completed_sales_amount' => fn ($paymentQuery) => $paymentQuery->where('status', 'completed'),
            ], 'amount');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        } else {
            $query->whereIn('status', ['pending', 'draft', 'published']);
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhereHas('instructor', fn ($instructorQuery) => $instructorQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $courses = $query
            ->orderByRaw("FIELD(status, 'pending', 'draft', 'published', 'archived')")
            ->orderByDesc('submitted_for_review_at')
            ->orderByDesc('updated_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($courses);
    }

    public function show(Request $request, Course $course)
    {
        $course->load([
            'instructor:id,name,email,avatar,bio',
            'approver:id,name,email',
            'category:id,name,slug',
            'modules.lessons' => fn ($lessonQuery) => $lessonQuery
                ->with(['contentable', 'interactiveConfig'])
                ->orderBy('sort_order'),
            'payments' => fn ($paymentQuery) => $paymentQuery
                ->with(['user:id,name,email', 'reviewer:id,name,email'])
                ->latest()
                ->limit(10),
        ]);

        $course->setAttribute('active_students', $course->enrollments()
            ->whereHas('user', fn ($userQuery) => $userQuery
                ->whereNotNull('last_active_at')
                ->where('last_active_at', '>=', now()->subDays(7)))
            ->count());

        $course->setAttribute('completed_sales_amount', (float) $course->payments()
            ->where('status', 'completed')
            ->sum('amount'));

        $course->setAttribute('average_learning_score', round((float) $course->interactiveActivityResults()->avg('score'), 2));

        return response()->json($course);
    }

    public function updateStatus(Request $request, Course $course)
    {
        $validated = $request->validate([
            'status' => 'required|in:draft,pending,published',
            'review_notes' => 'nullable|string|max:2000',
        ]);

        $course = $this->courseWorkflowService->transition(
            $course,
            $request->user(),
            $validated['status'],
            $validated['review_notes'] ?? null
        );

        return response()->json([
            'message' => match ($validated['status']) {
                'published' => 'Curso aprobado y publicado correctamente.',
                'pending' => 'Curso devuelto a la bandeja de revisión.',
                default => 'Curso movido a borrador correctamente.',
            },
            'course' => $course,
        ]);
    }
}
