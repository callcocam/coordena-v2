<?php

namespace Database\Factories;

use App\Enums\ExchangeOfferStatus;
use App\Models\ExchangeInviteSend;
use App\Models\ExchangeOffer;
use App\Models\Speaker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeOffer>
 */
class ExchangeOfferFactory extends Factory
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
            'direction' => 'incoming',
            'speaker_id' => Speaker::factory(),
            'status' => ExchangeOfferStatus::Draft,
        ];
    }

    /**
     * Indicate that the offer was selected by the coordinator.
     */
    public function selected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExchangeOfferStatus::Selected,
        ]);
    }
}
