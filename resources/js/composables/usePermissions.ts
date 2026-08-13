import { usePage } from '@inertiajs/vue3';

/**
 * Server-driven UI gating. `can(ability)` reads the `permissions` map that the
 * backend shares through Inertia; an absent or unknown ability resolves to
 * `false`. While no RBAC backend shares that map, every check returns `false`,
 * so gate UI defensively: prefer HIDING what can't be done (`v-if`) over merely
 * disabling it.
 */
export function usePermissions() {
    const page = usePage();

    const can = (ability: string): boolean =>
        page.props.permissions?.[ability] ?? false;

    const canAny = (...abilities: string[]): boolean =>
        abilities.some((ability) => can(ability));

    return { can, canAny };
}
