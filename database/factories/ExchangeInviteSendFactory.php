<?php

namespace Database\Factories;

use App\Enums\ExchangeInviteSendStatus;
use App\Models\Congregation;
use App\Models\ExchangeInvite;
use App\Models\ExchangeInviteSend;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeInviteSend>
 */
class ExchangeInviteSendFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invite_id' => ExchangeInvite::factory(),
            'congregation_id' => Congregation::factory(),
            'channel' => 'whatsapp',
            'status' => ExchangeInviteSendStatus::Pending,
        ];
    }
}
