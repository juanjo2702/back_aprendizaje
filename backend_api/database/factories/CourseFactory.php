<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        $instructor = User::where('role', 'instructor')->inRandomOrder()->first()
            ?? User::factory()->instructor()->create();

        $category = Category::inRandomOrder()->first()
            ?? Category::factory()->create();

        $levels = ['beginner', 'intermediate', 'advanced', 'all_levels'];
        $statuses = ['draft', 'pending', 'published', 'archived'];
        $title = fake()->unique()->sentence(5);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => fake()->paragraphs(3, true),
            'short_description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 0, 199.99),
            'thumbnail' => 'https://images.unsplash.com/photo-'.fake()->numberBetween(1500000000, 1600000000).'?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
            'promo_video' => 'https://www.youtube.com/watch?v='.fake()->regexify('[A-Za-z0-9_-]{11}'),
            'instructor_id' => $instructor->id,
            'category_id' => $category->id,
            'level' => fake()->randomElement($levels),
            'language' => fake()->randomElement(['es', 'en', 'pt']),
            'status' => fake()->randomElement($statuses),
            'requirements' => [fake()->sentence(), fake()->sentence()],
            'what_you_learn' => [fake()->sentence(), fake()->sentence(), fake()->sentence()],
            'has_certificate' => fake()->boolean(70),
            'certificate_min_score' => fake()->numberBetween(70, 90),
        ];
    }
}
