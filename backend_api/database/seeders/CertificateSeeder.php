<?php

namespace Database\Seeders;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Seeder;

class CertificateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = CertificateTemplate::all();
        $defaultTemplate = $templates->where('is_default', true)->first() ?? $templates->first();

        // Certificado para el estudiante de demostración
        $demoStudent = User::where('email', 'estudiante@plataforma.com')->first();
        if ($demoStudent) {
            $enrollments = Enrollment::where('user_id', $demoStudent->id)
                ->where('progress', '>=', 100)
                ->get();

            foreach ($enrollments as $enrollment) {
                $course = $enrollment->course;
                if ($course->has_certificate) {
                    Certificate::create([
                        'user_id' => $demoStudent->id,
                        'course_id' => $course->id,
                        'template_id' => $defaultTemplate->id,
                        'certificate_code' => 'CERT-'.strtoupper(uniqid()),
                        'final_score' => rand($course->certificate_min_score, 100),
                        'issued_at' => now()->subDays(rand(1, 30)),
                        'expiry_date' => rand(0, 1) ? now()->addYears(2) : null,
                        'download_url' => 'https://plataforma.com/certificados/'.uniqid(),
                    ]);
                }
            }
        }

        // Certificados para otros estudiantes
        $students = User::where('role', 'student')->where('id', '!=', $demoStudent->id ?? 0)->get();
        foreach ($students as $student) {
            $enrollments = Enrollment::where('user_id', $student->id)
                ->where('progress', '>=', 100)
                ->get();

            foreach ($enrollments as $enrollment) {
                $course = $enrollment->course;
                if ($course->has_certificate && rand(0, 1)) {
                    Certificate::create([
                        'user_id' => $student->id,
                        'course_id' => $course->id,
                        'template_id' => $templates->random()->id,
                        'certificate_code' => 'CERT-'.strtoupper(uniqid()),
                        'final_score' => rand($course->certificate_min_score, 100),
                        'issued_at' => now()->subDays(rand(1, 90)),
                        'expiry_date' => rand(0, 1) ? now()->addYears(1) : null,
                        'download_url' => 'https://plataforma.com/certificados/'.uniqid(),
                    ]);
                }
            }
        }
    }
}
