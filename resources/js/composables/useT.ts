import { usePage } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';

/**
 * Nested translation tree as shared from the backend via Inertia props.
 */
export type TranslationTree = {
    [key: string]: string | TranslationTree;
};

export type TranslationReplacements = Record<string, string | number>;

export type UseTReturn = {
    t: (key: string, replacements?: TranslationReplacements) => string;
    locale: ComputedRef<string>;
};

/**
 * Resolve a dotted key against the shared translation tree.
 *
 * Returns the raw string when found, otherwise `undefined` so the caller can
 * fall back to the key itself (making missing translations visible on screen).
 */
function resolve(tree: TranslationTree, key: string): string | undefined {
    let current: string | TranslationTree | undefined = tree;

    for (const segment of key.split('.')) {
        if (typeof current !== 'object' || current === null) {
            return undefined;
        }

        current = current[segment];
    }

    return typeof current === 'string' ? current : undefined;
}

/**
 * Replace `:name` placeholders in a translation string.
 */
function interpolate(
    message: string,
    replacements: TranslationReplacements,
): string {
    return Object.entries(replacements).reduce(
        (result, [token, value]) =>
            result.replaceAll(`:${token}`, String(value)),
        message,
    );
}

/**
 * Lightweight i18n composable backed by Inertia shared props.
 *
 * Reads `page.props.translations` and navigates dotted keys such as
 * `app.auth.login.title`. Missing keys return the key itself so gaps surface
 * directly in the UI.
 */
export function useT(): UseTReturn {
    const page = usePage();

    const locale = computed<string>(
        () => (page.props.locale as string) ?? 'pt_BR',
    );

    function t(
        key: string,
        replacements: TranslationReplacements = {},
    ): string {
        const tree =
            (page.props.translations as TranslationTree | undefined) ?? {};
        const message = resolve(tree, key);

        if (message === undefined) {
            return key;
        }

        return interpolate(message, replacements);
    }

    return { t, locale };
}
