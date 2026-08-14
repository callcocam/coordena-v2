<?php

namespace Database\Factories;

use App\Enums\CongregationIntroStatus;
use App\Models\Congregation;
use App\Models\CongregationIntro;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CongregationIntro>
 */
class CongregationIntroFactory extends Factory
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
            'congregation_id' => Congregation::factory(),
            'channel' => 'whatsapp',
            'portal_token' => Str::random(40),
            'status' => CongregationIntroStatus::Pending,
        ];
    }

    /**
     * The intro was delivered and awaits the partner's answer.
     */
    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => CongregationIntroStatus::Sent,
            'wamid' => 'wamid.'.Str::random(24),
            'sent_at' => now(),
        ]);
    }

    /**
     * The partner accepted the intro.
     */
    public function accepted(): static
    {
        return $this->sent()->state(fn (): array => [
            'status' => CongregationIntroStatus::Accepted,
            'responded_at' => now(),
        ]);
    }

    /**
     * The partner declined the intro.
     */
    public function declined(): static
    {
        return $this->sent()->state(fn (): array => [
            'status' => CongregationIntroStatus::Declined,
            'responded_at' => now(),
            'declined_at' => now(),
        ]);
    }
}
