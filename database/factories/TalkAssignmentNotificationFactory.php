<?php

namespace Database\Factories;

use App\Enums\SpeakerNotificationKind;
use App\Enums\SpeakerNotificationStatus;
use App\Models\Speaker;
use App\Models\TalkAssignment;
use App\Models\TalkAssignmentNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TalkAssignmentNotification>
 */
class TalkAssignmentNotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'talk_assignment_id' => TalkAssignment::factory(),
            'speaker_id' => Speaker::factory(),
            'kind' => SpeakerNotificationKind::Assignment,
            'status' => SpeakerNotificationStatus::Pending,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SpeakerNotificationStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SpeakerNotificationStatus::Confirmed,
            'sent_at' => now()->subHour(),
            'responded_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SpeakerNotificationStatus::Failed,
        ]);
    }

    public function reminder(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => SpeakerNotificationKind::Reminder,
        ]);
    }
}
