<?php

namespace App\Http\Controllers\PublicTalks;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicTalks\SavePublicTalkSettingsRequest;
use App\Models\TalkAssignment;
use App\Services\PublicTalks\PublicTalkSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A tela de configurações do módulo de discursos: prazos de lembrete ao
 * orador, alerta de pendências ao coordenador e reengate/expiração das
 * trocas, com override por time e fallback para `config/public_talks.php`.
 */
class SettingsController extends Controller
{
    public function __construct(
        protected PublicTalkSettings $settings,
    ) {}

    /**
     * Show the effective values, the global defaults and what the team
     * customized.
     */
    public function show(Request $request, string $current_team): Response
    {
        $team = $request->user()->currentTeam;

        Gate::authorize('viewAny', [TalkAssignment::class, $team]);

        $teamSettings = $this->settings->for($team);

        return Inertia::render('publicTalks/Settings', [
            'settings' => $teamSettings->all(),
            'defaults' => PublicTalkSettings::defaults(),
            'overrides' => $teamSettings->overrides(),
            'canManage' => Gate::allows('create', [TalkAssignment::class, $team]),
        ]);
    }

    /**
     * Persist the overrides (empty field = back to the global default).
     */
    public function update(SavePublicTalkSettingsRequest $request, string $current_team): RedirectResponse
    {
        $this->settings->for($request->user()->currentTeam)->save($request->overrides());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Configurações salvas.')]);

        return back();
    }
}
