<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\CertificateAutomationService;
use App\Services\CourseProgressService;
use Illuminate\Http\Request;

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
        $certificate = $this->certificateAutomationService->issueIfEligible(
            $user,
            $course,
            (float) $snapshot['overall_progress'],
            'progress_endpoint'
        );

        return response()->json([
            'progress' => $snapshot,
            'certificate' => $certificate,
        ]);
    }
}
