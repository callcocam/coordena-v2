<?php

namespace Database\Factories;

use App\Models\ExchangeInviteSend;
use App\Models\ExchangeMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeMessage>
 */
class ExchangeMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invite_send_id' => ExchangeInviteSend::factory(),
            'direction' => 'inbound',
            'channel' => 'whatsapp',
            'body' => fake()->sentence(),
        ];
    }
}
