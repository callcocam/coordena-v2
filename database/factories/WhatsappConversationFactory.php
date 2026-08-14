<?php

namespace Database\Factories;

use App\Models\Coordinator;
use App\Models\Team;
use App\Models\WhatsappConversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappConversation>
 */
class WhatsappConversationFactory extends Factory
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
            'phone' => '55519'.fake()->numerify('########'),
            'coordinator_id' => Coordinator::factory(),
            'state' => 'menu',
            'context' => [],
            'last_message_at' => now(),
            'expires_at' => now()->addDay(),
        ];
    }

    /**
     * Indicate that the 24h window of the conversation is gone.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_message_at' => now()->subDays(2),
            'expires_at' => now()->subDay(),
        ]);
    }
}
