<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'avatar' => 'https://i.pravatar.cc/300?img='.rand(1, 70),
            'role' => 'student',
            'bio' => fake()->paragraph(),
            'total_points' => rand(0, 1000),
            'current_streak' => rand(0, 14),
            'last_active_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => 'admin',
            'total_points' => fake()->numberBetween(1200, 2200),
            'current_streak' => fake()->numberBetween(7, 20),
            'last_active_at' => fake()->dateTimeBetween('-3 days', 'now'),
        ]);
    }

    public function instructor(): static
    {
        return $this->state(fn () => [
            'role' => 'instructor',
            'total_points' => fake()->numberBetween(650, 1800),
            'current_streak' => fake()->numberBetween(3, 15),
            'last_active_at' => fake()->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    public function student(): static
    {
        return $this->state(fn () => [
            'role' => 'student',
            'total_points' => fake()->numberBetween(0, 1600),
            'current_streak' => fake()->numberBetween(0, 12),
            'last_active_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
