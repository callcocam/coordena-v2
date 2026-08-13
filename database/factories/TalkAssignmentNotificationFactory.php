<?php

namespace Database\Factories;

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
            'kind' => 'assignment',
            'status' => 'pending',
        ];
    }
}
