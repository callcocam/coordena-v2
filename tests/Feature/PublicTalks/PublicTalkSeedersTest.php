<?php

use App\Models\Congregation;
use App\Models\PublicTalkOutline;
use App\Models\Speaker;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\CongregationSeeder;
use Database\Seeders\PublicTalkDemoSeeder;
use Database\Seeders\PublicTalkOutlineSeeder;

test('outline seeder is idempotent', function () {
    $this->seed(PublicTalkOutlineSeeder::class);
    $count = PublicTalkOutline::query()->count();

    expect($count)->toBeGreaterThan(0);

    $this->seed(PublicTalkOutlineSeeder::class);

    expect(PublicTalkOutline::query()->count())->toBe($count);
});

test('congregation seeder is idempotent', function () {
    if (! file_exists(database_path('data/congregations.local.php'))) {
        $this->markTestSkipped('Arquivo local de congregações não disponível.');
    }

    User::factory()->create();

    $this->seed(CongregationSeeder::class);
    $count = Congregation::query()->count();

    expect($count)->toBeGreaterThan(0);

    Congregation::query()->each(function (Congregation $congregation) {
        expect($congregation->meeting_weekday)->toBeIn([Carbon::SATURDAY, Carbon::SUNDAY])
            ->and(substr($congregation->meeting_time, 0, 5))->toBeIn(['18:00', '18:30', '19:00', '19:30', '20:00']);
    });

    $this->seed(CongregationSeeder::class);

    expect(Congregation::query()->count())->toBe($count);
});

test('demo seeder is idempotent and builds the demo team', function () {
    $owner = User::factory()->create();

    $this->seed(PublicTalkOutlineSeeder::class);
    Congregation::factory()->count(3)->create(['owner_user_id' => $owner->id]);

    $this->seed(PublicTalkDemoSeeder::class);

    $team = Team::query()->where('name', 'Arranjo de Oradores (Demo)')->first();

    expect($team)->not->toBeNull()
        ->and($team->home_congregation_id)->not->toBeNull()
        ->and($team->coordinators()->count())->toBeGreaterThan(0);

    $speakers = Speaker::query()->count();

    $this->seed(PublicTalkDemoSeeder::class);

    expect(Speaker::query()->count())->toBe($speakers)
        ->and(Team::query()->where('name', 'Arranjo de Oradores (Demo)')->count())->toBe(1);
});
