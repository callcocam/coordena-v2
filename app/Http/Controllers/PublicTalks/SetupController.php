<?php

namespace App\Http\Controllers\PublicTalks;

use App\Enums\CoordinatorRole;
use App\Enums\ExchangeOpt;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicTalks\SetupCongregationRequest;
use App\Http\Requests\PublicTalks\SetupCoordinatorRequest;
use App\Models\Congregation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SetupController extends Controller
{
    /**
     * Define the team's home congregation, reusing one from the acervo or
     * creating a fresh entry on the owner's acervo.
     */
    public function storeCongregation(SetupCongregationRequest $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        $congregationId = $request->validated('congregation_id');

        if ($congregationId === null) {
            $congregationId = Congregation::query()->create([
                'owner_user_id' => $team->owner()->id,
                'name' => $request->validated('name'),
                'city' => $request->validated('city'),
                'meeting_weekday' => $request->validated('meeting_weekday'),
                'meeting_time' => $request->validated('meeting_time'),
                'exchange_opt' => ExchangeOpt::Unknown,
            ])->id;
        }

        $team->fill(['home_congregation_id' => $congregationId])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Congregação da casa definida.')]);

        return to_route('public-talks.schedule', ['current_team' => $team->slug]);
    }

    /**
     * Register the responsible coordinator (plus optional helpers) and
     * unlock the schedule.
     */
    public function storeCoordinator(SetupCoordinatorRequest $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        $team->coordinators()->create([
            'name' => $request->validated('name'),
            'phone' => $request->validated('phone'),
            'role' => CoordinatorRole::Responsible,
            'is_active' => true,
        ]);

        foreach ($request->validated('helpers', []) as $helper) {
            $team->coordinators()->create([
                'name' => $helper['name'],
                'phone' => $helper['phone'] ?? null,
                'role' => CoordinatorRole::Helper,
                'is_active' => true,
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Coordenador responsável definido.')]);

        return to_route('public-talks.schedule', ['current_team' => $team->slug]);
    }
}
