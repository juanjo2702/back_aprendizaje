<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CourseStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_instructor_can_send_a_course_to_review_and_return_it_to_draft(): void
    {
        $instructor = User::factory()->instructor()->create();
        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'status' => 'draft',
        ]);

        $this->actingAs($instructor, 'sanctum');

        $pendingResponse = $this->putJson("/api/courses/{$course->id}/status", [
            'status' => 'pending',
        ]);

        $pendingResponse
            ->assertOk()
            ->assertJsonPath('course.status', 'pending');

        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'status' => 'pending',
        ]);

        $draftResponse = $this->putJson("/api/courses/{$course->id}/status", [
            'status' => 'draft',
        ]);

        $draftResponse
            ->assertOk()
            ->assertJsonPath('course.status', 'draft');
    }

    #[Test]
    public function an_instructor_cannot_publish_a_course_directly(): void
    {
        $instructor = User::factory()->instructor()->create();
        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'status' => 'draft',
        ]);

        $this->actingAs($instructor, 'sanctum');

        $response = $this->putJson("/api/courses/{$course->id}/status", [
            'status' => 'published',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['status']);

        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'status' => 'draft',
        ]);
    }

    #[Test]
    public function an_admin_can_publish_a_pending_course_from_the_review_queue(): void
    {
        $admin = User::factory()->admin()->create();
        $course = Course::factory()->create([
            'status' => 'pending',
        ]);

        $this->actingAs($admin, 'sanctum');

        $response = $this->putJson("/api/admin/courses/{$course->id}/approval-status", [
            'status' => 'published',
            'review_notes' => 'Contenido validado por QA y aprobado por administración.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('course.status', 'published')
            ->assertJsonPath('course.approved_by', $admin->id);

        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'status' => 'published',
            'approved_by' => $admin->id,
        ]);
    }
}
