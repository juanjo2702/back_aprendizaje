<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use App\Services\CertificateAutomationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CertificateController extends Controller
{
    public function __construct(
        private readonly CertificateAutomationService $certificateAutomationService
    ) {
    }

    /**
     * Listar certificados del usuario autenticado.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Certificate::where('user_id', $user->id)
            ->with([
                'course:id,title,slug,thumbnail',
                'template:id,name,background_image',
            ]);

        // Filtrar por curso
        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        // Ordenar por fecha de emisión (más reciente primero)
        $query->orderBy('issued_at', 'desc');

        return $query->paginate($request->get('per_page', 10));
    }

    /**
     * Generar un nuevo certificado (si se cumplen los requisitos).
     */
    public function generate(Request $request, Course $course)
    {
        $user = Auth::user();

        // Verificar que el curso tenga certificados habilitados
        if (! $course->has_certificate) {
            return response()->json([
                'message' => 'Este curso no ofrece certificado.',
            ], 422);
        }

        // Verificar que el usuario esté inscrito en el curso
        $enrollment = $course->enrollments()->where('user_id', $user->id)->first();
        if (! $enrollment) {
            return response()->json([
                'message' => 'No estás inscrito en este curso.',
            ], 403);
        }

        // Verificar que el progreso sea suficiente
        if ($enrollment->progress < 100) {
            return response()->json([
                'message' => 'Debes completar el curso al 100% para obtener el certificado.',
                'current_progress' => $enrollment->progress,
                'required_progress' => 100,
            ], 422);
        }

        // Verificar puntaje mínimo en evaluaciones (si aplica)
        if ($course->certificate_requires_final_exam) {
            $examScope = $course->certificate_exam_scope ?? 'lesson';

            if ($examScope === 'course') {
                // Calcular promedio de TODAS las actividades interactivas del curso
                $courseScore = $this->calculateAllActivitiesAverage($user, $course);

                if ($courseScore < $course->certificate_min_score) {
                    return response()->json([
                        'message' => 'Debes aprobar las actividades de todo el curso para obtener el certificado.',
                        'current_score' => round($courseScore, 2),
                        'required_score' => $course->certificate_min_score,
                    ], 422);
                }
            } else {
                // Scope 'lesson': verificar lección específica
                $finalExamLesson = $course->certificateFinalLesson()->first();

                if (! $finalExamLesson) {
                    return response()->json([
                        'message' => 'El docente todavía no configuró el examen final de certificación.',
                    ], 422);
                }

                $finalExamResult = \App\Models\InteractiveActivityResult::query()
                    ->where('user_id', $user->id)
                    ->where('course_id', $course->id)
                    ->where('lesson_id', $finalExamLesson->id)
                    ->latest('last_attempt_at')
                    ->first();

                $finalExamScore = $finalExamResult
                    ? (((float) $finalExamResult->score / max(1, (float) $finalExamResult->max_score)) * 100)
                    : 0;

                if (! $finalExamResult || $finalExamResult->status !== 'completed' || $finalExamScore < $course->certificate_min_score) {
                    return response()->json([
                        'message' => "Debes aprobar el examen final \"{$finalExamLesson->title}\" para obtener el certificado.",
                        'current_score' => round($finalExamScore, 2),
                        'required_score' => $course->certificate_min_score,
                    ], 422);
                }
            }
        } elseif ($course->certificate_min_score > 0) {
            // Sin examen final requerido: calcular promedio general del curso
            $avgScore = $this->calculateCourseAverage($user, $course);

            if ($avgScore < $course->certificate_min_score) {
                return response()->json([
                    'message' => 'No alcanzaste el puntaje mínimo requerido para el certificado.',
                    'current_score' => round($avgScore, 2),
                    'required_score' => $course->certificate_min_score,
                ], 422);
            }
        }

        $existingCertificate = Certificate::where('user_id', $user->id)->where('course_id', $course->id)->first();
        if ($existingCertificate) {
            return response()->json([
                'message' => 'Ya tienes un certificado para este curso.',
                'certificate' => $existingCertificate->load(['course', 'template']),
            ]);
        }

        $certificate = $this->certificateAutomationService->issueIfEligible($user, $course, 100, 'manual_request');

        return response()->json([
            'message' => 'Certificado generado exitosamente.',
            'certificate' => $certificate->load(['course', 'template']),
        ], 201);
    }

    /**
     * Ver detalles de un certificado específico.
     */
    public function show(Certificate $certificate)
    {
        $user = Auth::user();

        // Verificar que el certificado pertenezca al usuario (o sea admin/instructor)
        if ($certificate->user_id !== $user->id && ! $user->isAdmin() && ! $user->isInstructor()) {
            abort(403, 'No tienes permiso para ver este certificado.');
        }

        $certificate->load([
            'course:id,title,slug,description,instructor_id',
            'course.instructor:id,name,avatar',
            'template:id,name,background_image,template_config',
        ]);

        return response()->json($certificate);
    }

    /**
     * Verificar la validez de un certificado por código.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'certificate_code' => 'required|string',
        ]);

        $certificate = Certificate::where('certificate_code', $request->certificate_code)
            ->with([
                'user:id,name,email',
                'course:id,title,slug,instructor_id',
                'course.instructor:id,name',
                'template:id,name',
            ])
            ->first();

        if (! $certificate) {
            return response()->json([
                'valid' => false,
                'message' => 'Certificado no encontrado.',
            ]);
        }

        // Verificar si ha expirado
        $isExpired = $certificate->expiry_date && Carbon::now()->gt($certificate->expiry_date);

        return response()->json([
            'valid' => ! $isExpired,
            'expired' => $isExpired,
            'certificate' => $certificate->makeHidden(['metadata']),
            'verification_date' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function verifyByCode(string $certificateCode)
    {
        return $this->verify(new Request([
            'certificate_code' => $certificateCode,
        ]));
    }

    /**
     * Descargar certificado como PDF.
     */
    public function download(Certificate $certificate)
    {
        $user = Auth::user();

        // Verificar permisos
        if ($certificate->user_id !== $user->id && ! $user->isAdmin() && ! $user->isInstructor()) {
            abort(403, 'No tienes permiso para descargar este certificado.');
        }

        $certificate->load(['course', 'template', 'user']);

        if (! $certificate->pdf_path || ! Storage::disk('public')->exists($certificate->pdf_path)) {
            $certificate = $this->certificateAutomationService->persistPdf($certificate);
        }

        return Storage::disk('public')->download(
            $certificate->pdf_path,
            "certificado-{$certificate->certificate_code}.pdf"
        );
    }

    /**
     * Calcular promedio de TODAS las actividades interactivas del curso.
     */
    private function calculateAllActivitiesAverage(User $user, Course $course): float
    {
        $results = \App\Models\InteractiveActivityResult::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', 'completed')
            ->get();

        if ($results->isEmpty()) {
            return 0.0;
        }

        $totalPercentage = $results->sum(function ($result) {
            $maxScore = max(1, (float) $result->max_score);
            return ((float) $result->score / $maxScore) * 100;
        });

        return $totalPercentage / $results->count();
    }

    /**
     * Calcular promedio de score del usuario en el curso.
     */
    private function calculateCourseAverage(User $user, Course $course): float
    {
        // Obtener promedio de quizzes completados en el curso
        $avg = DB::table('user_quiz_attempts')
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', 'completed')
            ->avg('percentage');

        // Si no hay quizzes, usar progreso como base
        if (! $avg) {
            $enrollment = $course->enrollments()->where('user_id', $user->id)->first();

            return $enrollment ? $enrollment->progress : 0;
        }

        return (float) $avg;
    }
}
