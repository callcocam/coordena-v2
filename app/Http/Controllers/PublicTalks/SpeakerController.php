<?php

namespace App\Http\Controllers\PublicTalks;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicTalks\SaveSpeakerRequest;
use App\Models\Congregation;
use App\Models\Speaker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class SpeakerController extends Controller
{
    /**
     * Store a new speaker on the congregation.
     */
    public function store(SaveSpeakerRequest $request, string $current_team, Congregation $congregation): RedirectResponse
    {
        $this->ensureAcervo($request, $congregation);
        Gate::authorize('create', Speaker::class);

        $speaker = $congregation->speakers()->create($request->speakerAttributes());
        $speaker->outlines()->sync($request->validated('outline_ids', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Orador cadastrado.')]);

        return back();
    }

    /**
     * Update the speaker and its prepared outlines.
     */
    public function update(SaveSpeakerRequest $request, string $current_team, Congregation $congregation, Speaker $speaker): RedirectResponse
    {
        $this->ensureAcervo($request, $congregation);
        abort_if($speaker->congregation_id !== $congregation->id, 404);
        Gate::authorize('update', $speaker);

        $speaker->update($request->speakerAttributes());
        $speaker->outlines()->sync($request->validated('outline_ids', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Orador atualizado.')]);

        return back();
    }

    /**
     * Soft delete the speaker.
     */
    public function destroy(Request $request, string $current_team, Congregation $congregation, Speaker $speaker): RedirectResponse
    {
        $this->ensureAcervo($request, $congregation);
        abort_if($speaker->congregation_id !== $congregation->id, 404);
        Gate::authorize('delete', $speaker);

        $speaker->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Orador removido.')]);

        return back();
    }

    /**
     * The congregation must belong to the acervo of the current team's owner.
     */
    protected function ensureAcervo(Request $request, Congregation $congregation): void
    {
        $owner = $request->user()->currentTeam?->owner();

        abort_if($owner === null || $congregation->owner_user_id !== $owner->id, 404);
    }
}
