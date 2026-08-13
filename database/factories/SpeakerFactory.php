<?php

namespace Database\Factories;

use App\Enums\SpeakerRole;
use App\Models\Congregation;
use App\Models\Speaker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Speaker>
 */
class SpeakerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'congregation_id' => Congregation::factory(),
            'name' => fake()->name('male'),
            'role' => fake()->randomElement([SpeakerRole::Elder, SpeakerRole::MinisterialServant]),
            'phone' => '519'.fake()->numerify('########'),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the speaker is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
