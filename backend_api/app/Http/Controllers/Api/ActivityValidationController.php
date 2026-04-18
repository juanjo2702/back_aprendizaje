<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InteractiveConfig;
use App\Models\User;
use App\Services\ActivityAttemptService;
use Illuminate\Http\Request;

class ActivityValidationController extends Controller
{
    public function __construct(
        private readonly ActivityAttemptService $activityAttemptService
    ) {
    }

    public function store(Request $request, InteractiveConfig $interactiveConfig)
    {
        $validated = $request->validate([
            'score' => 'required|numeric|min:0|max:1000',
            'max_score' => 'nullable|numeric|min:1|max:1000',
            'time_spent_seconds' => 'nullable|integer|min:0|max:86400',
            'answers' => 'nullable|array',
            'meta' => 'nullable|array',
        ]);

        $result = $this->activityAttemptService->submit(
            $request->user(),
            $interactiveConfig,
            $validated['score'],
            $validated['max_score'] ?? 100,
            [
                'time_spent_seconds' => $validated['time_spent_seconds'] ?? 0,
                'answers' => $validated['answers'] ?? [],
                'meta' => $validated['meta'] ?? [],
            ]
        );

        return response()->json(
            collect($result)->except(['status_code'])->all(),
            $result['status_code']
        );
    }

    public function reset(Request $request, InteractiveConfig $interactiveConfig, User $student)
    {
        $course = $interactiveConfig->course;
        $user = $request->user();

        if (! $user->isAdmin() && ((int) $course->instructor_id !== (int) $user->id)) {
            return response()->json(['message' => 'No puedes resetear actividades de este curso.'], 403);
        }

        $result = $this->activityAttemptService->resetForStudent($interactiveConfig, $student);

        return response()->json([
            'message' => 'Actividad reabierta para el alumno.',
            'activity_result' => $result,
        ]);
    }
}
