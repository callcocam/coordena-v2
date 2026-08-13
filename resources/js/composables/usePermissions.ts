import { usePage } from '@inertiajs/vue3';
import type { Cargo } from '@/types';

/**
 * Server-driven UI gating. `can(ability)` reads the `permissions` map and
 * `hasCargo(key)` reads the `cargos` list that the backend shares through
 * Inertia for the user's current team; both resolve to `false` for an absent
 * team, ability, or cargo. Gate UI defensively: prefer HIDING what can't be
 * done (`v-if`) over merely disabling it.
 */
export function usePermissions() {
    const page = usePage();

    const can = (ability: string): boolean =>
        page.props.permissions?.[ability] ?? false;

    const canAny = (...abilities: string[]): boolean =>
        abilities.some((ability) => can(ability));

    const hasCargo = (key: string): boolean =>
        (page.props.cargos as Cargo[] | undefined)?.some(
            (cargo) => cargo.key === key,
        ) ?? false;

    return { can, canAny, hasCargo };
}
