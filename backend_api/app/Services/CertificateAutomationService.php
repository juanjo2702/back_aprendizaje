<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\InteractiveActivityResult;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CertificateAutomationService
{
    public function qualification(User $user, Course $course, float $progressPercentage): array
    {
        if (! $course->has_certificate) {
            return ['qualifies' => false, 'reason' => 'Este curso no ofrece certificado.', 'score' => 0];
        }

        if ($progressPercentage < 100) {
            return ['qualifies' => false, 'reason' => 'El progreso todavía no llegó al 100%.', 'score' => 0];
        }

        if ($course->certificate_requires_final_exam) {
            $lesson = $course->certificateFinalLesson()->first();
            if (! $lesson) {
                return ['qualifies' => false, 'reason' => 'Falta configurar el examen final.', 'score' => 0];
            }

            $result = InteractiveActivityResult::query()
                ->where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->where('lesson_id', $lesson->id)
                ->latest('last_attempt_at')
                ->first();

            $score = $result ? (((float) $result->score / max(1, (float) $result->max_score)) * 100) : 0;

            return [
                'qualifies' => $result && $result->status === 'completed' && $score >= (int) $course->certificate_min_score,
                'reason' => $result
                    ? "El examen final requiere {$course->certificate_min_score}%."
                    : 'Todavía no hay examen final aprobado.',
                'score' => $score,
            ];
        }

        $score = $this->calculateCourseAverage($user, $course);

        return [
            'qualifies' => $score >= (int) $course->certificate_min_score,
            'reason' => "Se requiere al menos {$course->certificate_min_score}% de promedio.",
            'score' => $score,
        ];
    }

    public function issueIfEligible(User $user, Course $course, float $progressPercentage, string $issuedVia = 'auto_progress'): ?Certificate
    {
        $existing = Certificate::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $qualification = $this->qualification($user, $course, $progressPercentage);
        if (! $qualification['qualifies']) {
            return null;
        }

        return DB::transaction(function () use ($user, $course, $qualification, $issuedVia) {
            $template = CertificateTemplate::query()
                ->where('is_default', true)
                ->first()
                ?: CertificateTemplate::query()->first()
                ?: CertificateTemplate::create([
                    'name' => 'Plantilla automática',
                    'template_config' => ['layout' => 'default'],
                    'is_default' => true,
                ]);

            $certificate = Certificate::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'template_id' => $template?->id,
                'certificate_code' => 'CERT-'.strtoupper(uniqid()).'-'.$user->id.'-'.$course->id,
                'student_name' => $user->name,
                'course_name' => $course->title,
                'final_score' => round((float) $qualification['score'], 2),
                'issued_at' => now(),
                'metadata' => [
                    'instructor' => $course->instructor?->name,
                    'completion_date' => now()->format('Y-m-d'),
                    'progress_percentage' => $progressPercentage,
                ],
                'issued_via' => $issuedVia,
            ]);

            $this->persistPdf($certificate->fresh(['user', 'course', 'template']));

            return $certificate->fresh(['course', 'template']);
        });
    }

    public function persistPdf(Certificate $certificate): Certificate
    {
        $verificationUrl = rtrim(config('app.url'), '/')."/api/certificates/verify/{$certificate->certificate_code}";
        $qrSvg = QrCode::format('svg')->size(140)->generate($verificationUrl);
        $pdf = Pdf::loadView('certificates.template', [
            'certificate' => $certificate,
            'verificationUrl' => $verificationUrl,
            'qrCodeBase64' => 'data:image/svg+xml;base64,'.base64_encode($qrSvg),
            'issuedDate' => optional($certificate->issued_at)->format('d/m/Y'),
        ]);

        $relativePath = "certificates/{$certificate->certificate_code}.pdf";
        Storage::disk('public')->put($relativePath, $pdf->output());

        $certificate->forceFill([
            'pdf_path' => $relativePath,
            'verification_url' => $verificationUrl,
            'download_url' => rtrim(config('app.url'), '/')."/api/certificates/{$certificate->id}/download",
        ])->save();

        return $certificate;
    }

    public function calculateCourseAverage(User $user, Course $course): float
    {
        $results = InteractiveActivityResult::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', 'completed')
            ->get();

        if ($results->isEmpty()) {
            return 100.0;
        }

        return (float) $results->avg(function (InteractiveActivityResult $result) {
            return ((float) $result->score / max(1, (float) $result->max_score)) * 100;
        });
    }
}
