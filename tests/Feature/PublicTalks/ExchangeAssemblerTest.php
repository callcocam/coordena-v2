<?php

use App\Enums\ExchangeInviteSendStatus;
use App\Enums\ExchangeOfferStatus;
use App\Enums\TalkAssignmentStatus;
use App\Enums\TalkAssignmentType;
use App\Models\Congregation;
use App\Models\ExchangeInvite;
use App\Models\ExchangeInviteSend;
use App\Models\ExchangeOffer;
use App\Models\PublicTalkOutline;
use App\Models\Speaker;
use App\Models\TalkAssignment;
use App\Models\Team;
use App\Services\PublicTalks\ExchangeAssembler;
use App\Services\PublicTalks\ExchangeInviteManager;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @return array{0: ExchangeInviteSend, 1: Carbon}
 */
function assemblerSend(): array
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
        'status' => ExchangeInviteSendStatus::Answered,
    ]);

    return [$send, $month];
}

function incomingOfferFor(ExchangeInviteSend $send, Carbon $date): ExchangeOffer
{
    $offer = ExchangeOffer::factory()->create([
        'invite_send_id' => $send->id,
        'speaker_id' => Speaker::factory()->create(['congregation_id' => $send->congregation_id])->id,
        'target_date' => $date->toDateString(),
    ]);

    $offer->outlines()->sync([PublicTalkOutline::factory()->create()->id]);

    return $offer->refresh();
}

test('choosing an outline stores it on the incoming offer', function () {
    [$send, $month] = assemblerSend();
    $offer = incomingOfferFor($send, $month->copy()->addDays(5));
    $outline = $offer->outlines->first();

    app(ExchangeAssembler::class)->chooseOutline($offer, $outline);

    expect($offer->refresh()->chosen_outline_id)->toBe($outline->id);
});

test('rejects an outline the offer does not propose', function () {
    [$send, $month] = assemblerSend();
    $offer = incomingOfferFor($send, $month->copy()->addDays(5));

    app(ExchangeAssembler::class)->chooseOutline($offer, PublicTalkOutline::factory()->create());
})->throws(ValidationException::class);

test('rejects choosing an outline for an outgoing offer', function () {
    [$send, $month] = assemblerSend();

    $offer = ExchangeOffer::factory()->create([
        'invite_send_id' => $send->id,
        'direction' => 'outgoing',
        'target_date' => $month->copy()->addDays(5)->toDateString(),
    ]);

    app(ExchangeAssembler::class)->chooseOutline($offer, PublicTalkOutline::factory()->create());
})->throws(ValidationException::class);

test('accepting an incoming offer without a chosen theme is rejected', function () {
    [$send, $month] = assemblerSend();
    $offer = incomingOfferFor($send, $month->copy()->addDays(5));

    app(ExchangeAssembler::class)->accept($offer);
})->throws(ValidationException::class);

test('accepting an incoming offer books our open week right away', function () {
    [$send, $month] = assemblerSend();
    $date = $month->copy()->addDays(5);

    $week = TalkAssignment::factory()->create([
        'team_id' => $send->invite->team_id,
        'date' => $date->toDateString(),
    ]);
    $offer = incomingOfferFor($send, $date);
    $assembler = app(ExchangeAssembler::class);
    $assembler->chooseOutline($offer, $offer->outlines->first());

    $assembler->accept($offer->refresh());

    $week->refresh();
    expect($offer->refresh()->status)->toBe(ExchangeOfferStatus::Accepted)
        ->and($week->type)->toBe(TalkAssignmentType::Incoming)
        ->and($week->status)->toBe(TalkAssignmentStatus::Scheduled)
        ->and($week->speaker_id)->toBe($offer->speaker_id)
        ->and($week->outline_id)->toBe($offer->chosen_outline_id);
});

test('accepting an outgoing offer registers the trip of our speaker', function () {
    [$send, $month] = assemblerSend();

    $offer = ExchangeOffer::factory()->create([
        'invite_send_id' => $send->id,
        'direction' => 'outgoing',
        'target_date' => $month->copy()->addDays(12)->toDateString(),
    ]);

    app(ExchangeAssembler::class)->accept($offer);

    $trip = TalkAssignment::query()
        ->where('team_id', $send->invite->team_id)
        ->where('type', TalkAssignmentType::Outgoing)
        ->first();

    expect($offer->refresh()->status)->toBe(ExchangeOfferStatus::Accepted)
        ->and($trip)->not->toBeNull()
        ->and($trip->speaker_id)->toBe($offer->speaker_id);
});

test('declining an accepted incoming offer reopens the week for the rotation', function () {
    [$send, $month] = assemblerSend();
    $date = $month->copy()->addDays(5);

    $week = TalkAssignment::factory()->create([
        'team_id' => $send->invite->team_id,
        'date' => $date->toDateString(),
    ]);
    $offer = incomingOfferFor($send, $date);
    $assembler = app(ExchangeAssembler::class);
    $assembler->chooseOutline($offer, $offer->outlines->first());
    $assembler->accept($offer->refresh());

    $assembler->decline($offer->refresh());

    $week->refresh();
    expect($offer->refresh()->status)->toBe(ExchangeOfferStatus::Declined)
        ->and($week->type)->toBe(TalkAssignmentType::Home)
        ->and($week->status)->toBe(TalkAssignmentStatus::Open)
        ->and($week->speaker_id)->toBeNull()
        ->and(app(ExchangeInviteManager::class)->openWeeks($send->invite)->pluck('id'))->toContain($week->id);
});

test('declining an accepted outgoing offer removes the trip', function () {
    [$send, $month] = assemblerSend();

    $offer = ExchangeOffer::factory()->create([
        'invite_send_id' => $send->id,
        'direction' => 'outgoing',
        'target_date' => $month->copy()->addDays(12)->toDateString(),
    ]);
    $assembler = app(ExchangeAssembler::class);
    $assembler->accept($offer);

    $assembler->decline($offer->refresh());

    expect($offer->refresh()->status)->toBe(ExchangeOfferStatus::Declined)
        ->and(TalkAssignment::query()->where('type', TalkAssignmentType::Outgoing)->count())->toBe(0);
});

test('confirming closes the package and logs a summary with declined lines', function () {
    [$send, $month] = assemblerSend();
    $date = $month->copy()->addDays(5);

    TalkAssignment::factory()->create([
        'team_id' => $send->invite->team_id,
        'date' => $date->toDateString(),
    ]);
    $accepted = incomingOfferFor($send, $date);
    $declined = incomingOfferFor($send, $month->copy()->addDays(12));
    $assembler = app(ExchangeAssembler::class);
    $assembler->chooseOutline($accepted, $accepted->outlines->first());
    $assembler->accept($accepted->refresh());
    $assembler->decline($declined);

    $summary = $assembler->confirm($send->refresh());

    expect($accepted->refresh()->status)->toBe(ExchangeOfferStatus::Confirmed)
        ->and($send->refresh()->status)->toBe(ExchangeInviteSendStatus::Answered)
        ->and($summary)->toContain($accepted->speaker->name)
        ->and($summary)->toContain($declined->speaker->name)
        ->and($send->messages()->where('direction', 'outbound')->count())->toBe(1);
});

test('confirming without accepted offers is rejected', function () {
    [$send] = assemblerSend();

    app(ExchangeAssembler::class)->confirm($send);
})->throws(ValidationException::class);

test('a confirmed offer can no longer be declined', function () {
    [$send, $month] = assemblerSend();
    $date = $month->copy()->addDays(5);

    TalkAssignment::factory()->create([
        'team_id' => $send->invite->team_id,
        'date' => $date->toDateString(),
    ]);
    $offer = incomingOfferFor($send, $date);
    $assembler = app(ExchangeAssembler::class);
    $assembler->chooseOutline($offer, $offer->outlines->first());
    $assembler->accept($offer->refresh());
    $assembler->confirm($send->refresh());

    $assembler->decline($offer->refresh());
})->throws(ValidationException::class);
