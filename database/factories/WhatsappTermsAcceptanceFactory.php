<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use App\Models\WhatsappTermsAcceptance;
use App\Support\WhatsappTerms;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappTermsAcceptance>
 */
class WhatsappTermsAcceptanceFactory extends Factory
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
            'user_id' => User::factory(),
            'version' => WhatsappTerms::VERSION,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'accepted_at' => now(),
        ];
    }
}
