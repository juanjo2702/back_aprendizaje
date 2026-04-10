<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserProgressControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_dashboard_stats_for_authenticated_user()
    {
        // Create a test user
        $user = User::factory()->create();

        // Authenticate the user
        $this->actingAs($user, 'sanctum');

        // Make request to dashboard stats endpoint
        $response = $this->getJson('/api/user/dashboard-stats');

        // Assert successful response
        $response->assertStatus(200);

        // Assert response structure
        $response->assertJsonStructure([
            'user' => ['name', 'email'],
            'stats' => ['total_points', 'current_streak', 'last_active_at', 'points_this_month'],
            'courses' => ['total', 'completed', 'in_progress', 'recent'],
            'activities' => ['recent_games', 'recent_quizzes', 'recent_certificates'],
            'achievements' => ['total_badges', 'total_certificates', 'total_games_completed', 'total_quizzes_completed'],
        ]);
    }

    #[Test]
    public function it_requires_authentication()
    {
        // Make request without authentication
        $response = $this->getJson('/api/user/dashboard-stats');

        // Assert unauthorized response
        $response->assertStatus(401);
    }

    #[Test]
    public function it_returns_user_courses_with_filtering()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        // Test with default parameters
        $response = $this->getJson('/api/user/courses');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'current_page',
            'per_page',
            'total',
        ]);

        // Test with status filter
        $response = $this->getJson('/api/user/courses?status=completed');
        $response->assertStatus(200);

        // Test with sort parameter
        $response = $this->getJson('/api/user/courses?sort=progress');
        $response->assertStatus(200);
    }

    #[Test]
    public function it_returns_course_progress_for_enrolled_course()
    {
        // This test would require creating a course, enrollment, modules, lessons, etc.
        // For now, we'll create a simple test structure
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        // We need to create a course and enroll the user
        // Since this is a complex setup, we'll mark it as incomplete for now
        // and focus on the basic authentication tests
        $this->markTestIncomplete('Requires complex test data setup');
    }

    #[Test]
    public function it_returns_recent_activity()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/user/recent-activity');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total_activities',
            'activities',
        ]);
    }
}
