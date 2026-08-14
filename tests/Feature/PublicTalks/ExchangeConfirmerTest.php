<?php

use App\Enums\ExchangeInviteSendStatus;
use App\Enums\ExchangeOfferStatus;
use App\Enums\TalkAssignmentStatus;
use App\Enums\TalkAssignmentType;
use App\Models\Congregation;
use App\Models\ExchangeInvite;
use App\Models\ExchangeInviteSend;
use App\Models\ExchangeOffer;
use App\Models\Speaker;
use App\Models\TalkAssignment;
use App\Models\Team;
use App\Services\PublicTalks\ExchangeConfirmer;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @return array{0: ExchangeInviteSend, 1: Carbon}
 */
function confirmerSend(): array
{
    $team = Team::factory()->create();
    $month = Carbon::today()->addMonth()->startOfMonth();

    $invite = ExchangeInvite::factory()->create([
        'team_id' => $team->id,
        'month' => $month->toDateString(),
    ]);
    $send = ExchangeInviteSend::factory()->create([
        'invite_id' => $invite->id,
        'congregation_id' => Congregation::factory()->create()->id,
        'status' => ExchangeInviteSendStatus::Sent,
    ]);

    return [$send, $month];
}

test('confirming an incoming offer fills our open week', function () {
    [$send, $month] = confirmerSend();
    $date = $month->copy()->addDays(5);

    $week = TalkAssignment::factory()->create([
        'team_id' => $send->invite->team_id,
        'date' => $date->toDateString(),
    ]);
    $offer = ExchangeOffer::factory()->create([
        'invite_send_id' => $send->id,
        'speaker_id' => Speaker::factory()->create(['congregation_id' => $send->congregation_id])->id,
        'target_date' => $date->toDateString(),
    ]);

    $summary = app(ExchangeConfirmer::class)->confirm($send, [$offer->id]);

    $week->refresh();
    expect($week->type)->toBe(TalkAssignmentType::Incoming)
        ->and($week->status)->toBe(TalkAssignmentStatus::Scheduled)
        ->and($week->speaker_id)->toBe($offer->speaker_id)
        ->and($week->counterpart_congregation_id)->toBe($send->congregation_id)
        ->and($offer->refresh()->status)->toBe(ExchangeOfferStatus::Confirmed)
        ->and($send->refresh()->status)->toBe(ExchangeInviteSendStatus::Answered)
        ->and($summary)->not->toBe('');
});

test('confirming an outgoing offer registers the trip of our speaker', function () {
    [$send, $month] = confirmerSend();
    $date = $month->copy()->addDays(12);

    $offer = ExchangeOffer::factory()->create([
        'invite_send_id' => $send->id,
        'direction' => 'outgoing',
        'target_date' => $date->toDateString(),
    ]);

    app(ExchangeConfirmer::class)->confirm($send, [$offer->id]);

    $trip = TalkAssignment::query()
        ->where('team_id', $send->invite->team_id)
        ->where('type', TalkAssignmentType::Outgoing)
        ->first();

    expect($trip)->not->toBeNull()
        ->and($trip->speaker_id)->toBe($offer->speaker_id)
        ->and($trip->status)->toBe(TalkAssignmentStatus::Scheduled)
        ->and($trip->counterpart_congregation_id)->toBe($send->congregation_id);
});

test('rejects an incoming offer when the week is already taken', function () {
    [$send, $month] = confirmerSend();
    $date = $month->copy()->addDays(5);

    TalkAssignment::factory()->create([
        'team_id' => $send->invite->team_id,
        'date' => $date->toDateString(),
        'status' => TalkAssignmentStatus::Scheduled,
    ]);
    $offer = ExchangeOffer::factory()->create([
        'invite_send_id' => $send->id,
        'target_date' => $date->toDateString(),
    ]);

    app(ExchangeConfirmer::class)->confirm($send, [$offer->id]);
})->throws(ValidationException::class);

test('rejects an offer dated outside the invite month', function () {
    [$send, $month] = confirmerSend();

    $offer = ExchangeOffer::factory()->create([
        'invite_send_id' => $send->id,
        'target_date' => $month->copy()->addMonths(2)->toDateString(),
    ]);

    app(ExchangeConfirmer::class)->confirm($send, [$offer->id]);
})->throws(ValidationException::class);

test('rejects an offer without a target date', function () {
    [$send] = confirmerSend();

    $offer = ExchangeOffer::factory()->create([
        'invite_send_id' => $send->id,
        'target_date' => null,
    ]);

    app(ExchangeConfirmer::class)->confirm($send, [$offer->id]);
})->throws(ValidationException::class);
