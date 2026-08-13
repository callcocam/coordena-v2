<?php

namespace Database\Factories;

use App\Enums\ExchangeInviteStatus;
use App\Models\ExchangeInvite;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeInvite>
 */
class ExchangeInviteFactory extends Factory
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
            'month' => now()->addMonth()->startOfMonth()->toDateString(),
            'status' => ExchangeInviteStatus::Open,
        ];
    }
}
