<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PublicTalks\CongregationController;
use App\Http\Controllers\PublicTalks\CoordinatorController;
use App\Http\Controllers\PublicTalks\ExchangeController;
use App\Http\Controllers\PublicTalks\ExchangeOfferController;
use App\Http\Controllers\PublicTalks\ExchangePortalController;
use App\Http\Controllers\PublicTalks\ExchangeSendController;
use App\Http\Controllers\PublicTalks\ScheduleController;
use App\Http\Controllers\PublicTalks\SetupController;
use App\Http\Controllers\PublicTalks\SpeakerController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');
Route::inertia('/termos', 'legal/Terms')->name('legal.terms');
Route::inertia('/privacidade', 'legal/Privacy')->name('legal.privacy');

Route::middleware('throttle:20,1')->group(function () {
    Route::get('permuta/{portal_token}', [ExchangePortalController::class, 'show'])->name('exchange.portal');
    Route::post('permuta/{portal_token}', [ExchangePortalController::class, 'store'])->name('exchange.portal.submit');
});

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('discursos', [ScheduleController::class, 'index'])->name('public-talks.schedule');
        Route::put('discursos/semanas/{assignment}', [ScheduleController::class, 'update'])->name('public-talks.schedule.update');

        Route::post('discursos/setup/congregacao', [SetupController::class, 'storeCongregation'])->name('public-talks.setup.congregation');
        Route::post('discursos/setup/coordenador', [SetupController::class, 'storeCoordinator'])->name('public-talks.setup.coordinator');

        Route::get('discursos/coordenadores', [CoordinatorController::class, 'index'])->name('public-talks.coordinators.index');
        Route::post('discursos/coordenadores', [CoordinatorController::class, 'store'])->name('public-talks.coordinators.store');
        Route::put('discursos/coordenadores/{coordinator}', [CoordinatorController::class, 'update'])->name('public-talks.coordinators.update');
        Route::delete('discursos/coordenadores/{coordinator}', [CoordinatorController::class, 'destroy'])->name('public-talks.coordinators.destroy');

        Route::get('acervo', [CongregationController::class, 'index'])->name('acervo.congregations.index');
        Route::post('acervo', [CongregationController::class, 'store'])->name('acervo.congregations.store');
        Route::get('acervo/{congregation}', [CongregationController::class, 'show'])->name('acervo.congregations.show');
        Route::put('acervo/{congregation}', [CongregationController::class, 'update'])->name('acervo.congregations.update');
        Route::delete('acervo/{congregation}', [CongregationController::class, 'destroy'])->name('acervo.congregations.destroy');

        Route::get('discursos/permutas', [ExchangeController::class, 'index'])->name('public-talks.exchange.index');
        Route::post('discursos/permutas/envios', [ExchangeController::class, 'storeSend'])->name('public-talks.exchange.sends.store');

        Route::get('discursos/permutas/envios/{send}', [ExchangeSendController::class, 'show'])->name('public-talks.exchange.sends.show');
        Route::post('discursos/permutas/envios/{send}/respostas', [ExchangeSendController::class, 'storeReply'])->name('public-talks.exchange.sends.replies.store');
        Route::post('discursos/permutas/envios/{send}/recusar', [ExchangeSendController::class, 'decline'])->name('public-talks.exchange.sends.decline');
        Route::post('discursos/permutas/envios/{send}/confirmar', [ExchangeSendController::class, 'confirm'])->name('public-talks.exchange.sends.confirm');

        Route::post('discursos/permutas/envios/{send}/ofertas', [ExchangeOfferController::class, 'store'])->name('public-talks.exchange.offers.store');
        Route::put('discursos/permutas/envios/{send}/ofertas/{offer}', [ExchangeOfferController::class, 'update'])->name('public-talks.exchange.offers.update');
        Route::delete('discursos/permutas/envios/{send}/ofertas/{offer}', [ExchangeOfferController::class, 'destroy'])->name('public-talks.exchange.offers.destroy');

        Route::post('acervo/{congregation}/oradores', [SpeakerController::class, 'store'])->name('acervo.speakers.store');
        Route::put('acervo/{congregation}/oradores/{speaker}', [SpeakerController::class, 'update'])->name('acervo.speakers.update');
        Route::delete('acervo/{congregation}/oradores/{speaker}', [SpeakerController::class, 'destroy'])->name('acervo.speakers.destroy');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
