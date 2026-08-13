<?php

namespace Database\Factories;

use App\Enums\PublicTalkOutlineStatus;
use App\Models\PublicTalkOutline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublicTalkOutline>
 */
class PublicTalkOutlineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numberBetween(1, 500),
            'title' => fake()->sentence(6),
            'status' => PublicTalkOutlineStatus::Active,
        ];
    }
}
