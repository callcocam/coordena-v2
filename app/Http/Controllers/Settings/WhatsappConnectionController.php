<?php

namespace App\Http\Controllers\Settings;

use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\WhatsappTermsAcceptance;
use App\Support\WhatsappTerms;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WhatsappConnectionController extends Controller
{
    /**
     * Record the current user's acceptance of the WhatsApp terms of use for the
     * current team, capturing the IP and user agent as proof.
     */
    public function agree(Request $request): RedirectResponse
    {
        $team = $this->authorizedTeam($request);

        WhatsappTermsAcceptance::create([
            'team_id' => $team->id,
            'user_id' => $request->user()->id,
            'version' => WhatsappTerms::VERSION,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'accepted_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.whatsapp.terms.accepted_at')]);

        return to_route('profile.edit');
    }

    /**
     * Toggle the team between automatic (API) and manual WhatsApp mode. Only
     * flips the flag — the stored Meta credentials stay in place.
     */
    public function updateMode(Request $request): RedirectResponse
    {
        $team = $this->authorizedTeam($request);

        $validated = $request->validate([
            'api_enabled' => ['required', 'boolean'],
        ]);

        $team->update(['whatsapp_api_enabled' => $validated['api_enabled']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.whatsapp.mode.saved')]);

        return to_route('profile.edit');
    }

    /**
     * Save the team's Meta Cloud API credentials (pasted from the Business
     * Manager). This is the only way the team connects — the number is registered
     * on the Meta side, so there is no QR code.
     */
    public function updateCloud(Request $request): RedirectResponse
    {
        $team = $this->authorizedTeam($request);

        $validated = $request->validate([
            'phone_number_id' => ['required', 'string', 'max:255'],
            'cloud_access_token' => ['required', 'string', 'max:2000'],
            'waba_id' => ['nullable', 'string', 'max:255'],
            'app_id' => ['nullable', 'string', 'max:255'],
            'verified_name' => ['nullable', 'string', 'max:255'],
        ]);

        $connection = $team->whatsappConnection()->firstOrNew([]);
        $connection->fill([
            'phone_number_id' => $validated['phone_number_id'],
            'cloud_access_token' => $validated['cloud_access_token'],
            'waba_id' => $validated['waba_id'] ?? null,
            'app_id' => $validated['app_id'] ?? null,
            'verified_name' => $validated['verified_name'] ?? null,
        ]);
        $connection->team()->associate($team);
        $connection->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.whatsapp.cloud.saved')]);

        return to_route('profile.edit');
    }

    /**
     * Resolve the current team and ensure the user may manage its connection.
     */
    protected function authorizedTeam(Request $request): Team
    {
        $user = $request->user();
        $team = $user->currentTeam;

        abort_if($team === null, 403);
        abort_unless($user->hasTeamPermission($team, TeamPermission::ManageWhatsapp), 403);

        return $team;
    }
}
