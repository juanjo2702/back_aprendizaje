<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Services\CertificateAutomationService;
use App\Services\CourseProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProgressController extends Controller
{
    public function __construct(
        private readonly CourseProgressService $courseProgressService,
        private readonly CertificateAutomationService $certificateAutomationService
    ) {
    }

    public function show(Request $request, Course $course)
    {
        $user = $request->user();
        $snapshot = $this->courseProgressService->recalculateEnrollmentProgress($user, $course);
        $certificate = null;

        try {
            $certificate = $this->certificateAutomationService->issueIfEligible(
                $user,
                $course,
                (float) $snapshot['overall_progress'],
                'progress_endpoint'
            );
        } catch (\Throwable $exception) {
            Log::error('No se pudo emitir o recuperar el certificado desde el endpoint de progreso.', [
                'user_id' => $user->id,
                'course_id' => $course->id,
                'progress_percentage' => (float) ($snapshot['overall_progress'] ?? 0),
                'error' => $exception->getMessage(),
            ]);

            $certificate = Certificate::query()
                ->where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->first();
        }

        return response()->json([
            'progress' => $snapshot,
            'certificate' => $certificate,
        ]);
    }
}
