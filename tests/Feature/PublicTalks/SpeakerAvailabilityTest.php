<?php

use App\Models\Congregation;
use App\Models\ExchangeInvite;
use App\Models\ExchangeInviteSend;
use App\Models\ExchangeOffer;
use App\Models\Speaker;
use App\Models\TalkAssignment;
use App\Models\Team;
use App\Services\PublicTalks\SpeakerAvailability;

beforeEach(function () {
    $this->month = now()->addMonth()->startOfMonth();
    $this->availability = app(SpeakerAvailability::class);
});

test('a speaker with a confirmed outgoing talk in the month cannot go out', function () {
    $speaker = Speaker::factory()->create();

    TalkAssignment::factory()->outgoing()->confirmed()->create([
        'speaker_id' => $speaker->id,
        'date' => $this->month->copy()->addDays(10)->toDateString(),
    ]);

    expect($this->availability->canGoOut($speaker, $this->month))->toBeFalse();
});

test('a booking in another team of the same owner also blocks the speaker', function () {
    $speaker = Speaker::factory()->create();
    $otherTeam = Team::factory()->create();

    TalkAssignment::factory()->incoming()->confirmed()->create([
        'team_id' => $otherTeam->id,
        'speaker_id' => $speaker->id,
        'date' => $this->month->copy()->addDays(5)->toDateString(),
    ]);

    expect($this->availability->canGoOut($speaker, $this->month))->toBeFalse();
});

test('bookings outside the month do not block', function () {
    $speaker = Speaker::factory()->create();

    TalkAssignment::factory()->outgoing()->confirmed()->create([
        'speaker_id' => $speaker->id,
        'date' => $this->month->copy()->addMonths(2)->toDateString(),
    ]);

    expect($this->availability->canGoOut($speaker, $this->month))->toBeTrue();
});

test('a selected offer with a target date in the month blocks the speaker', function () {
    $speaker = Speaker::factory()->create();

    ExchangeOffer::factory()->selected()->create([
        'speaker_id' => $speaker->id,
        'target_date' => $this->month->copy()->addDays(12)->toDateString(),
    ]);

    expect($this->availability->canGoOut($speaker, $this->month))->toBeFalse();
});

test('a selected offer without target date blocks via the invite month', function () {
    $speaker = Speaker::factory()->create();

    $invite = ExchangeInvite::factory()->create(['month' => $this->month->toDateString()]);
    $send = ExchangeInviteSend::factory()->create(['invite_id' => $invite->id]);

    ExchangeOffer::factory()->selected()->create([
        'invite_send_id' => $send->id,
        'speaker_id' => $speaker->id,
        'target_date' => null,
    ]);

    expect($this->availability->canGoOut($speaker, $this->month))->toBeFalse();
});

test('a draft offer does not block the speaker', function () {
    $speaker = Speaker::factory()->create();

    ExchangeOffer::factory()->create([
        'speaker_id' => $speaker->id,
        'target_date' => $this->month->copy()->addDays(12)->toDateString(),
    ]);

    expect($this->availability->canGoOut($speaker, $this->month))->toBeTrue();
});

test('availableFor returns only free active speakers with their outlines', function () {
    $congregation = Congregation::factory()->create();

    $free = Speaker::factory()->create(['congregation_id' => $congregation->id]);
    $inactive = Speaker::factory()->inactive()->create(['congregation_id' => $congregation->id]);
    $busy = Speaker::factory()->create(['congregation_id' => $congregation->id]);

    TalkAssignment::factory()->outgoing()->confirmed()->create([
        'speaker_id' => $busy->id,
        'date' => $this->month->copy()->addDays(3)->toDateString(),
    ]);

    $available = $this->availability->availableFor($congregation, $this->month);

    expect($available->pluck('id')->all())->toBe([$free->id])
        ->and($available->first()->relationLoaded('outlines'))->toBeTrue();
});
