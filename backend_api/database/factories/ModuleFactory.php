<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Module>
 */
class ModuleFactory extends Factory
{
    protected $model = Module::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title' => 'Módulo '.fake()->numberBetween(1, 8).': '.fake()->sentence(3),
            'description' => fake()->paragraph(),
            'sort_order' => fake()->numberBetween(1, 8),
        ];
    }
}
