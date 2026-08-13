<?php

namespace Database\Factories;

use App\Enums\ExchangeOpt;
use App\Models\Congregation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Congregation>
 */
class CongregationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_user_id' => User::factory(),
            'name' => 'Congregação '.fake()->unique()->streetName(),
            'city' => fake()->city(),
            'circuit' => 'RS-'.fake()->numberBetween(1, 99).' '.fake()->randomElement(['A', 'B']),
            'meeting_weekday' => fake()->randomElement([6, 0]),
            'meeting_time' => fake()->randomElement(['09:30', '14:00', '18:00']),
            'exchange_opt' => ExchangeOpt::Unknown,
        ];
    }

    /**
     * Indicate that the congregation opted in to exchanges.
     */
    public function optedIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'exchange_opt' => ExchangeOpt::OptedIn,
        ]);
    }
}
