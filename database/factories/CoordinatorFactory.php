<?php

namespace Database\Factories;

use App\Enums\CoordinatorRole;
use App\Models\Coordinator;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coordinator>
 */
class CoordinatorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->name('male'),
            'phone' => '519'.fake()->numerify('########'),
            'role' => CoordinatorRole::Helper,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the coordinator is the responsible one.
     */
    public function responsible(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => CoordinatorRole::Responsible,
        ]);
    }

    /**
     * Indicate that the coordinator is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
