<?php

namespace App\Http\Controllers\PublicTalks;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicTalks\SaveCongregationRequest;
use App\Models\Congregation;
use App\Models\PublicTalkOutline;
use App\Models\Speaker;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CongregationController extends Controller
{
    /**
     * List the congregations of the owner's acervo, with search.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Congregation::class);

        $team = $request->user()->currentTeam;
        $query = trim((string) $request->query('q', ''));

        $congregations = $this->acervoQuery($team)
            ->withCount('speakers')
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($builder) use ($query): void {
                    $builder->where('name', 'like', "%{$query}%")
                        ->orWhere('city', 'like', "%{$query}%")
                        ->orWhere('circuit', 'like', "%{$query}%");
                });
            })
            ->orderBy('name')
            ->get()
            ->map(fn (Congregation $congregation): array => [
                'id' => $congregation->id,
                'name' => $congregation->name,
                'city' => $congregation->city,
                'circuit' => $congregation->circuit,
                'meeting_weekday' => $congregation->meeting_weekday,
                'meeting_time' => $congregation->meeting_time,
                'speakers_count' => $congregation->speakers_count,
                'is_home' => $congregation->id === $team->home_congregation_id,
            ])
            ->all();

        return Inertia::render('publicTalks/congregations/Index', [
            'congregations' => $congregations,
            'filters' => ['q' => $query],
            'canManage' => Gate::allows('create', Congregation::class),
        ]);
    }

    /**
     * Store a new congregation on the owner's acervo.
     */
    public function store(SaveCongregationRequest $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        $congregation = Congregation::query()->create([
            ...$request->validated(),
            'owner_user_id' => $team->owner()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Congregação criada.')]);

        return to_route('acervo.congregations.show', [
            'current_team' => $team->slug,
            'congregation' => $congregation->id,
        ]);
    }

    /**
     * Show a congregation with its speakers.
     */
    public function show(Request $request, string $current_team, Congregation $congregation): Response
    {
        Gate::authorize('view', $congregation);

        $team = $request->user()->currentTeam;

        $congregation->load(['speakers' => function ($query): void {
            $query->with('outlines:id,number,title')->orderBy('name');
        }]);

        return Inertia::render('publicTalks/congregations/Show', [
            'congregation' => [
                'id' => $congregation->id,
                'name' => $congregation->name,
                'city' => $congregation->city,
                'circuit' => $congregation->circuit,
                'address' => $congregation->address,
                'contact_name' => $congregation->contact_name,
                'contact_phone' => $congregation->contact_phone,
                'contact_email' => $congregation->contact_email,
                'secretary_name' => $congregation->secretary_name,
                'secretary_phone' => $congregation->secretary_phone,
                'secretary_email' => $congregation->secretary_email,
                'meeting_weekday' => $congregation->meeting_weekday,
                'meeting_time' => $congregation->meeting_time,
                'exchange_opt' => $congregation->exchange_opt->value,
                'is_home' => $congregation->id === $team->home_congregation_id,
            ],
            'speakers' => $congregation->speakers
                ->map(fn (Speaker $speaker): array => [
                    'id' => $speaker->id,
                    'name' => $speaker->name,
                    'role' => $speaker->role->value,
                    'phone' => $speaker->phone,
                    'is_active' => $speaker->is_active,
                    'notes' => $speaker->notes,
                    'outline_ids' => $speaker->outlines->modelKeys(),
                ])
                ->all(),
            'outlines' => PublicTalkOutline::query()
                ->orderBy('number')
                ->get(['id', 'number', 'title'])
                ->map(fn (PublicTalkOutline $outline): array => [
                    'id' => $outline->id,
                    'number' => $outline->number,
                    'title' => $outline->title,
                ])
                ->all(),
            'canManage' => Gate::allows('update', $congregation),
        ]);
    }

    /**
     * Update the congregation details.
     */
    public function update(SaveCongregationRequest $request, string $current_team, Congregation $congregation): RedirectResponse
    {
        Gate::authorize('update', $congregation);

        $congregation->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Congregação atualizada.')]);

        return back();
    }

    /**
     * Soft delete the congregation, unless a team uses it as home.
     */
    public function destroy(Request $request, string $current_team, Congregation $congregation): RedirectResponse
    {
        Gate::authorize('delete', $congregation);

        $team = $request->user()->currentTeam;

        if (Team::query()->where('home_congregation_id', $congregation->id)->exists()) {
            throw ValidationException::withMessages([
                'congregation' => __('Esta congregação é a congregação da casa de um time e não pode ser removida.'),
            ]);
        }

        $congregation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Congregação removida.')]);

        return to_route('acervo.congregations.index', ['current_team' => $team->slug]);
    }

    /**
     * The acervo of the current team's owner.
     *
     * @return Builder<Congregation>
     */
    protected function acervoQuery(Team $team): Builder
    {
        $owner = $team->owner();

        return Congregation::query()->when(
            $owner !== null,
            fn ($query) => $query->ownedBy($owner->id),
            fn ($query) => $query->whereRaw('1 = 0'),
        );
    }
}
