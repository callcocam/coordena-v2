<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Schedule::command('public-talks:ensure-horizon')
    ->dailyAt('05:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('public-talks:send-speaker-reminders')
    ->dailyAt('08:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('public-talks:nudge-pending-invite-sends')
    ->dailyAt('09:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping()
    ->onOneServer();
