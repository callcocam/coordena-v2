<?php

namespace Database\Seeders;

use App\Enums\DefaultCargo;
use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the permission catalog and the global (catalog) cargos.
     *
     * Idempotent: safe to run repeatedly (updateOrCreate by name/key).
     */
    public function run(): void
    {
        $permissions = $this->seedPermissions();

        $this->seedGlobalCargos($permissions);
    }

    /**
     * Seed every permission in the catalog, keyed by name.
     *
     * @return array<string, Permission>
     */
    protected function seedPermissions(): array
    {
        $permissions = [];

        foreach (PermissionCatalog::groups() as $group => $entries) {
            foreach ($entries as $name => $label) {
                $permissions[$name] = Permission::query()->updateOrCreate(
                    ['name' => $name],
                    ['label' => $label, 'group' => $group],
                );
            }
        }

        return $permissions;
    }

    /**
     * Seed the global cargos and sync each cargo's permissions.
     *
     * @param  array<string, Permission>  $permissions
     */
    protected function seedGlobalCargos(array $permissions): void
    {
        foreach (DefaultCargo::cases() as $cargo) {
            $role = Role::query()->updateOrCreate(
                ['team_id' => null, 'key' => $cargo->value],
                [
                    'name' => $cargo->label(),
                    'is_default' => $cargo->isDefault(),
                    'is_super' => $cargo->isSuper(),
                ],
            );

            $permissionIds = collect($cargo->permissions())
                ->map(fn (string $name): ?string => $permissions[$name]?->id)
                ->filter()
                ->all();

            $role->permissions()->sync($permissionIds);
        }
    }
}
