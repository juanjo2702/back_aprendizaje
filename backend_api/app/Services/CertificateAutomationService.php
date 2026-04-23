<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\InteractiveActivityResult;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
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
        $data = $this->buildDocumentData($certificate);
        $pdfOutput = $this->generatePdfBinary($data);

        $relativePath = "certificates/{$certificate->certificate_code}.pdf";
        Storage::disk('public')->put($relativePath, $pdfOutput);

        $certificate->forceFill([
            'pdf_path' => $relativePath,
            'verification_url' => $data['verificationUrl'],
            'download_url' => rtrim(config('app.url'), '/')."/api/certificates/{$certificate->id}/download",
        ])->save();

        return $certificate;
    }

    public function renderPreviewHtml(Certificate $certificate, bool $autoPrint = false, bool $isEmbedded = false): string
    {
        $data = $this->buildDocumentData($certificate);

        return View::make('certificates.template', array_merge($data, [
            'autoPrint' => $autoPrint,
            'isPreview' => true,
            'isEmbedded' => $isEmbedded,
        ]))->render();
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

    private function buildDocumentData(Certificate $certificate): array
    {
        $verificationUrl = rtrim(config('app.url'), '/')."/api/certificates/verify/{$certificate->certificate_code}";
        $qrSvg = QrCode::format('svg')->size(140)->generate($verificationUrl);

        return [
            'certificate' => $certificate,
            'verificationUrl' => $verificationUrl,
            'qrCodeBase64' => 'data:image/svg+xml;base64,'.base64_encode($qrSvg),
            'issuedDate' => optional($certificate->issued_at)->format('d/m/Y'),
            'autoPrint' => false,
            'isPreview' => false,
        ];
    }

    private function generatePdfBinary(array $data): string
    {
        $domPdfFacade = 'Barryvdh\\DomPDF\\Facade\\Pdf';

        if (class_exists($domPdfFacade)) {
            $pdf = $domPdfFacade::loadView('certificates.template', $data)
                ->setPaper('a4', 'landscape');

            return $pdf->output();
        }

        return $this->buildSimplePdf($data);
    }

    private function buildSimplePdf(array $data): string
    {
        /** @var Certificate $certificate */
        $certificate = $data['certificate'];

        $lines = [
            'LMS Creator - Certificado de Finalizacion',
            '',
            'Se certifica que:',
            $certificate->student_name ?: $certificate->user?->name ?: 'Estudiante',
            '',
            'completo satisfactoriamente el curso:',
            $certificate->course_name ?: $certificate->course?->title ?: 'Curso sin titulo',
            '',
            'Codigo: '.($certificate->certificate_code ?: '-'),
            'Fecha de emision: '.($data['issuedDate'] ?: '-'),
            'Instructor: '.($certificate->course?->instructor?->name ?: ($certificate->metadata['instructor'] ?? '-')),
            'Puntaje final: '.number_format((float) $certificate->final_score, 2).'%',
            '',
            'Verificacion publica:',
            $data['verificationUrl'],
        ];

        $contentLines = [];
        $y = 760;

        foreach ($lines as $line) {
            $safeLine = $this->escapePdfText($line);
            $contentLines[] = sprintf('BT /F1 %s Tf 60 %s Td (%s) Tj ET', $line === '' ? '12' : '18', $y, $safeLine);
            $y -= ($line === '' ? 18 : 28);
        }

        $contentStream = implode("\n", $contentLines);
        $streamLength = strlen($contentStream);

        $objects = [
            "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj",
            "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj",
            "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj",
            "4 0 obj << /Length {$streamLength} >> stream\n{$contentStream}\nendstream endobj",
            "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$i])."\n";
        }

        $pdf .= "trailer << /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function escapePdfText(string $value): string
    {
        $latin1 = iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $value) ?: $value;

        return str_replace(
            ['\\', '(', ')', "\r", "\n"],
            ['\\\\', '\\(', '\\)', '', ' '],
            $latin1
        );
    }
}
