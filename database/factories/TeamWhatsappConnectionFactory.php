<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamWhatsappConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamWhatsappConnection>
 */
class TeamWhatsappConnectionFactory extends Factory
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
            'waba_id' => (string) fake()->numerify('###############'),
            'phone_number_id' => (string) fake()->numerify('###############'),
            'cloud_access_token' => 'EAA'.fake()->regexify('[A-Za-z0-9]{40}'),
            'app_id' => (string) fake()->numerify('###############'),
            'verified_name' => fake()->company(),
            'quality_rating' => 'GREEN',
            'messaging_limit' => 'TIER_1K',
        ];
    }

    /**
     * A connection with no Meta credentials — the team is not configured yet.
     */
    public function unconfigured(): static
    {
        return $this->state(fn (array $attributes): array => [
            'phone_number_id' => null,
            'cloud_access_token' => null,
        ]);
    }
}
