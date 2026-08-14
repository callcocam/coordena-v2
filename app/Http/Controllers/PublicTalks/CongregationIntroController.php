<?php

namespace App\Http\Controllers\PublicTalks;

use App\Enums\CongregationIntroStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SendCongregationIntro;
use App\Models\Congregation;
use App\Models\CongregationIntro;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CongregationIntroController extends Controller
{
    /**
     * Send (or resend) the WhatsApp intro to an acervo congregation. Only
     * one intro can wait for an answer per pair — a resend is always a new
     * row so the audit trail of previous attempts stays intact.
     */
    public function store(Request $request, string $current_team, Congregation $congregation): RedirectResponse
    {
        Gate::authorize('update', $congregation);

        $team = $request->user()->currentTeam;

        if (Phone::normalize($congregation->contact_phone) === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('app.public_talks.intro.no_whatsapp')]);

            return back();
        }

        $waiting = CongregationIntro::query()
            ->forPair($team, $congregation)
            ->whereIn('status', [CongregationIntroStatus::Pending, CongregationIntroStatus::Sent])
            ->exists();

        if ($waiting) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('app.public_talks.intro.already_pending')]);

            return back();
        }

        $intro = CongregationIntro::query()->create([
            'team_id' => $team->id,
            'congregation_id' => $congregation->id,
            'channel' => 'whatsapp',
            'portal_token' => Str::random(40),
            'status' => CongregationIntroStatus::Pending,
            'sent_by_id' => $request->user()->id,
        ]);

        SendCongregationIntro::dispatch($intro);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.public_talks.intro.queued')]);

        return back();
    }
}
