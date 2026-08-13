<?php

namespace App\Http\Controllers\Teams;

use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\SaveCargoRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CargoController extends Controller
{
    /**
     * Display the team's cargos management screen.
     */
    public function index(Team $team): Response
    {
        Gate::authorize('manageRoles', $team);

        $globalCargos = Role::query()
            ->global()
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'key' => $role->key,
                'name' => $role->name,
                'isSuper' => $role->is_super,
                'permissions' => $role->permissions->pluck('name')->all(),
            ]);

        $customCargos = $team->roles()
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'key' => $role->key,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->all(),
            ]);

        return Inertia::render('teams/Cargos', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
            ],
            'globalCargos' => $globalCargos,
            'customCargos' => $customCargos,
            'permissionGroups' => $this->permissionGroups(),
            'permissions' => request()->user()->toTeamPermissions($team),
        ]);
    }

    /**
     * Store a newly created custom cargo for the team.
     */
    public function store(SaveCargoRequest $request, Team $team): RedirectResponse
    {
        $role = $team->roles()->create([
            'key' => $request->cargoKey(),
            'name' => $request->validated('name'),
            'is_default' => false,
            'is_super' => false,
        ]);

        $role->permissions()->sync($this->permissionIds($request->validated('permissions', [])));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cargo criado.')]);

        return to_route('teams.cargos.index', ['team' => $team->slug]);
    }

    /**
     * Update the specified custom cargo.
     */
    public function update(SaveCargoRequest $request, Team $team, Role $role): RedirectResponse
    {
        abort_if($role->team_id !== $team->id, 404);

        $role->update([
            'key' => $request->cargoKey(),
            'name' => $request->validated('name'),
        ]);

        $role->permissions()->sync($this->permissionIds($request->validated('permissions', [])));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cargo atualizado.')]);

        return to_route('teams.cargos.index', ['team' => $team->slug]);
    }

    /**
     * Remove the specified custom cargo.
     */
    public function destroy(Team $team, Role $role): RedirectResponse
    {
        Gate::authorize('manageRoles', $team);

        abort_if($role->team_id !== $team->id, 404);

        DB::transaction(function () use ($role): void {
            DB::table('role_user')->where('role_id', $role->id)->delete();
            $role->permissions()->detach();
            $role->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cargo removido.')]);

        return to_route('teams.cargos.index', ['team' => $team->slug]);
    }

    /**
     * The selectable permissions grouped for the checkbox grid.
     *
     * `role:manage` is excluded so custom cargos cannot grant their own management rights.
     *
     * @return list<array{group: string, permissions: list<array{name: string, label: string}>}>
     */
    protected function permissionGroups(): array
    {
        $groups = [];

        foreach (PermissionCatalog::groups() as $group => $permissions) {
            $entries = [];

            foreach ($permissions as $name => $label) {
                if ($name === TeamPermission::ManageRoles->value) {
                    continue;
                }

                $entries[] = ['name' => $name, 'label' => $label];
            }

            if ($entries !== []) {
                $groups[] = ['group' => $group, 'permissions' => $entries];
            }
        }

        return $groups;
    }

    /**
     * Resolve permission names to their ids, ignoring the reserved management permission.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    protected function permissionIds(array $names): array
    {
        $names = array_diff($names, [TeamPermission::ManageRoles->value]);

        return Permission::query()
            ->whereIn('name', $names)
            ->pluck('id')
            ->all();
    }
}
