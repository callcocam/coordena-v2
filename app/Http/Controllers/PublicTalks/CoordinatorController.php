<?php

namespace App\Http\Controllers\PublicTalks;

use App\Enums\CoordinatorRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicTalks\SaveCoordinatorRequest;
use App\Models\Coordinator;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CoordinatorController extends Controller
{
    /**
     * List the team's coordenadores de discursos.
     */
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        Gate::authorize('viewAny', [Coordinator::class, $team]);

        return Inertia::render('publicTalks/Coordinators', [
            'coordinators' => $team->coordinators()
                ->orderBy('role')
                ->orderBy('name')
                ->get()
                ->map(fn (Coordinator $coordinator): array => [
                    'id' => $coordinator->id,
                    'name' => $coordinator->name,
                    'phone' => $coordinator->phone,
                    'role' => $coordinator->role->value,
                    'is_active' => $coordinator->is_active,
                ])
                ->all(),
            'canManage' => Gate::allows('create', [Coordinator::class, $team]),
        ]);
    }

    /**
     * Store a new coordinator for the team.
     */
    public function store(SaveCoordinatorRequest $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        $team->coordinators()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Coordenador adicionado.')]);

        return back();
    }

    /**
     * Update the coordinator, keeping at least one active responsible.
     */
    public function update(SaveCoordinatorRequest $request, string $current_team, Coordinator $coordinator): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        abort_if($coordinator->team_id !== $team->id, 404);
        Gate::authorize('update', $coordinator);

        $attributes = $request->validated();

        $this->ensureResponsibleRemains($team, $coordinator, $attributes);

        $coordinator->update($attributes);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Coordenador atualizado.')]);

        return back();
    }

    /**
     * Remove the coordinator, keeping at least one active responsible.
     */
    public function destroy(Request $request, string $current_team, Coordinator $coordinator): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        abort_if($coordinator->team_id !== $team->id, 404);
        Gate::authorize('delete', $coordinator);

        $this->ensureResponsibleRemains($team, $coordinator, null);

        $coordinator->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Coordenador removido.')]);

        return back();
    }

    /**
     * Block removing/demoting/deactivating the last active responsible.
     *
     * @param  array<string, mixed>|null  $attributes  `null` means the coordinator is being deleted.
     */
    protected function ensureResponsibleRemains(Team $team, Coordinator $coordinator, ?array $attributes): void
    {
        $wasActiveResponsible = $coordinator->role === CoordinatorRole::Responsible && $coordinator->is_active;

        if (! $wasActiveResponsible) {
            return;
        }

        $staysResponsible = $attributes !== null
            && ($attributes['role'] ?? null) === CoordinatorRole::Responsible->value
            && (bool) ($attributes['is_active'] ?? false);

        if ($staysResponsible) {
            return;
        }

        $hasAnotherResponsible = $team->coordinators()
            ->whereKeyNot($coordinator->id)
            ->active()
            ->where('role', CoordinatorRole::Responsible)
            ->exists();

        if (! $hasAnotherResponsible) {
            throw ValidationException::withMessages([
                'role' => __('Defina outro coordenador responsável ativo antes de remover ou desativar este.'),
            ]);
        }
    }
}
