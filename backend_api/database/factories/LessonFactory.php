<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lesson>
 */
class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    public function definition(): array
    {
        $type = fake()->randomElement(['video', 'video', 'resource', 'interactive']);
        $duration = match ($type) {
            'video' => fake()->numberBetween(480, 1500),
            'resource' => fake()->numberBetween(300, 900),
            'interactive' => fake()->numberBetween(360, 900),
            default => fake()->numberBetween(300, 1200),
        };

        return [
            'module_id' => Module::factory(),
            'title' => fake()->sentence(4),
            'type' => $type,
            'content_url' => $type === 'video'
                ? 'https://www.youtube.com/watch?v='.fake()->regexify('[A-Za-z0-9_-]{11}')
                : ($type === 'resource'
                    ? 'https://example.com/recursos/'.fake()->slug().'.pdf'
                    : null),
            'content_text' => $type === 'resource' ? fake()->paragraphs(2, true) : null,
            'duration' => $duration,
            'sort_order' => fake()->numberBetween(1, 12),
            'is_free' => false,
            'game_config_id' => null,
            'quiz_id' => null,
            'contentable_type' => null,
            'contentable_id' => null,
        ];
    }
}
