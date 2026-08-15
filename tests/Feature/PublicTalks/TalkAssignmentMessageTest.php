<?php

use App\Enums\SpeakerNotificationKind;
use App\Enums\TalkAssignmentType;
use App\Models\TalkAssignment;
use App\Services\PublicTalks\TalkAssignmentMessage;

test('template key follows the assignment direction', function (TalkAssignmentType $type, SpeakerNotificationKind $kind, string $expected) {
    $assignment = (new TalkAssignment)->forceFill(['type' => $type]);

    expect((new TalkAssignmentMessage)->templateKey($assignment, $kind))->toBe($expected);
})->with([
    'home assignment' => [TalkAssignmentType::Home, SpeakerNotificationKind::Assignment, 'talk_assignment'],
    'outgoing assignment' => [TalkAssignmentType::Outgoing, SpeakerNotificationKind::Assignment, 'talk_assignment'],
    'incoming assignment' => [TalkAssignmentType::Incoming, SpeakerNotificationKind::Assignment, 'talk_assignment_visitor'],
    'home reminder' => [TalkAssignmentType::Home, SpeakerNotificationKind::Reminder, 'talk_reminder'],
    'outgoing reminder' => [TalkAssignmentType::Outgoing, SpeakerNotificationKind::Reminder, 'talk_reminder_out'],
    'incoming reminder' => [TalkAssignmentType::Incoming, SpeakerNotificationKind::Reminder, 'talk_reminder_visitor'],
]);
