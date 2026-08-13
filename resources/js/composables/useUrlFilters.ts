import { watchDebounced } from '@vueuse/core';
import { computed, isRef, toValue } from 'vue';
import type { ComputedRef, MaybeRefOrGetter, Ref } from 'vue';

export type UrlFilterRef = Ref<string> | Ref<boolean>;

/** A filter that only accepts a known set of values from the query string. */
export type UrlFilterSpec = {
    ref: UrlFilterRef;
    /**
     * The values the filter accepts, read once while hydrating — pass a
     * computed or a getter when the options come from the page data. Values
     * that aren't strings never match, so an option list built from nullable
     * data can be handed over as it is.
     */
    allowed?: MaybeRefOrGetter<readonly (string | null)[]>;
};

export type UrlFilter = UrlFilterRef | UrlFilterSpec;

export type UseUrlFiltersReturn = {
    /**
     * The filters that differ from their default, as query-string values.
     * Empty when nothing is filtered — that's what keeps the URL clean.
     */
    query: ComputedRef<Record<string, string>>;
};

const parse = (value: string, fallback: string | boolean) =>
    typeof fallback === 'boolean' ? value === '1' || value === 'true' : value;

const serialize = (value: string | boolean) =>
    typeof value === 'boolean' ? (value ? '1' : '0') : value;

/**
 * Whether the filter takes this raw query value. A filter without an
 * allow-list takes anything — the URL is user input, and a `<Select>` handed a
 * value none of its items carry renders blank.
 */
const accepts = (spec: UrlFilterSpec, value: string) =>
    spec.allowed === undefined || toValue(spec.allowed).includes(value);

/**
 * Keep client-side list filters in the page URL.
 *
 * The refs are hydrated from the current query string on setup and written
 * back to it whenever they change, so reloading, bookmarking or sharing the
 * page restores the same view — and server-side actions (a PDF report, an
 * export) can be handed the very same query.
 *
 * The value each ref holds when the composable is called is its default, and
 * defaults are left out of the URL. Booleans travel as `1`/`0`.
 *
 * A filter given as `{ ref, allowed }` only takes values from its allow-list;
 * a hand-typed `?status=nonsense` is ignored and the default stands. Nothing
 * in the type system enforces this — a `Ref<'a' | 'b'>` happily accepts any
 * string at runtime — so the allow-list is the only guard a `<Select>` has.
 *
 * The URL is rewritten with `replaceState` (no Inertia visit, no history
 * entry): the data is already in the page, only the address bar catches up.
 */
export function useUrlFilters(
    filters: Record<string, UrlFilter>,
): UseUrlFiltersReturn {
    const entries = Object.entries(filters).map(
        ([key, filter]) =>
            [key, isRef(filter) ? { ref: filter } : filter] as const,
    );
    const defaults = Object.fromEntries(
        entries.map(([key, spec]) => [key, spec.ref.value]),
    );

    if (typeof window !== 'undefined') {
        const params = new URLSearchParams(window.location.search);

        entries.forEach(([key, spec]) => {
            const value = params.get(key);

            if (value !== null && accepts(spec, value)) {
                (spec.ref as Ref<string | boolean>).value = parse(
                    value,
                    defaults[key],
                );
            }
        });
    }

    const query = computed(() =>
        Object.fromEntries(
            entries
                .filter(([key, spec]) => spec.ref.value !== defaults[key])
                .map(([key, spec]) => [key, serialize(spec.ref.value)]),
        ),
    );

    // Debounced because a search box fires on every keystroke, and browsers
    // rate-limit replaceState. The computed above stays immediate, so anything
    // reading `query` (a report link, an export) always sees the live filters.
    watchDebounced(
        query,
        (current) => {
            if (typeof window === 'undefined') {
                return;
            }

            const search = new URLSearchParams(current).toString();
            const url =
                window.location.pathname + (search === '' ? '' : `?${search}`);

            // Keep Inertia's history state (page props, scroll) untouched — we
            // are only rewriting the address, not navigating.
            window.history.replaceState(window.history.state, '', url);
        },
        { debounce: 250 },
    );

    return { query };
}
