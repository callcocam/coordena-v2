<?php

namespace Database\Factories;

use App\Enums\TalkAssignmentStatus;
use App\Enums\TalkAssignmentType;
use App\Models\TalkAssignment;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TalkAssignment>
 */
class TalkAssignmentFactory extends Factory
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
            'date' => fake()->unique()->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
            'type' => TalkAssignmentType::Home,
            'status' => TalkAssignmentStatus::Open,
        ];
    }

    /**
     * Indicate that the assignment is confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TalkAssignmentStatus::Confirmed,
        ]);
    }

    /**
     * Indicate that the speaker goes out to another congregation.
     */
    public function outgoing(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TalkAssignmentType::Outgoing,
        ]);
    }

    /**
     * Indicate that the speaker comes from another congregation.
     */
    public function incoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TalkAssignmentType::Incoming,
        ]);
    }
}
