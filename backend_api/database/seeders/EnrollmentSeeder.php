<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Seeder;

class EnrollmentSeeder extends Seeder
{
    public function run(): void
    {
        $students = User::where('role', 'student')->get();
        $courses = Course::where('status', 'published')->get();

        // Inscripciones para el estudiante de demostración
        $demoStudent = User::where('email', 'estudiante@plataforma.com')->first();
        if ($demoStudent) {
            $demoCourses = $courses->take(3);
            foreach ($demoCourses as $course) {
                Enrollment::updateOrCreate(
                    [
                        'user_id' => $demoStudent->id,
                        'course_id' => $course->id,
                    ],
                    [
                        'progress' => rand(0, 100),
                        'enrolled_at' => now()->subDays(rand(1, 30)),
                    ]
                );
            }
        }

        // Inscripciones aleatorias para otros estudiantes
        foreach ($students as $student) {
            if ($student->email === 'estudiante@plataforma.com') {
                continue; // Ya procesado
            }

            $enrolledCourses = rand(1, 5);
            $randomCourses = $courses->random(min($enrolledCourses, $courses->count()));

            foreach ($randomCourses as $course) {
                Enrollment::updateOrCreate(
                    [
                        'user_id' => $student->id,
                        'course_id' => $course->id,
                    ],
                    [
                        'progress' => rand(0, 100),
                        'enrolled_at' => now()->subDays(rand(1, 90)),
                    ]
                );
            }
        }
    }
}
