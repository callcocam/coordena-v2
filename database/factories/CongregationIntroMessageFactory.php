<?php

namespace Database\Factories;

use App\Models\CongregationIntro;
use App\Models\CongregationIntroMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CongregationIntroMessage>
 */
class CongregationIntroMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'intro_id' => CongregationIntro::factory(),
            'direction' => 'outbound',
            'channel' => 'whatsapp',
            'body' => fake()->sentence(),
        ];
    }
}
