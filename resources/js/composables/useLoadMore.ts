import { computed, isRef, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';

export type LoadMoreSource<T> = Ref<T> | (() => T);

function read<T>(source: LoadMoreSource<T>): T {
    return isRef(source) ? source.value : source();
}

export type UseLoadMoreReturn<T> = {
    /** The slice of items currently visible. */
    visibleItems: ComputedRef<T[]>;
    /** Whether there are more items to reveal. */
    hasMore: ComputedRef<boolean>;
    /** How many items remain hidden. */
    remaining: ComputedRef<number>;
    /** Total number of items in the source. */
    total: ComputedRef<number>;
    /** Reveal the next chunk of items. */
    loadMore: () => void;
    /** Reset back to the first chunk. */
    reset: () => void;
};

/**
 * Client-side "load more" paging for lists whose full data is already loaded.
 *
 * Renders `pageSize` items at a time and reveals more on demand. The visible
 * count automatically resets whenever the source shrinks (e.g. a filter is
 * applied) so the button count stays accurate.
 */
export function useLoadMore<T>(
    source: LoadMoreSource<T[]>,
    pageSize = 15,
): UseLoadMoreReturn<T> {
    const items = computed<T[]>(() => read(source) ?? []);
    const limit = ref(pageSize);

    const total = computed(() => items.value.length);
    const visibleItems = computed(() => items.value.slice(0, limit.value));
    const hasMore = computed(() => limit.value < total.value);
    const remaining = computed(() => Math.max(0, total.value - limit.value));

    const loadMore = () => {
        limit.value = Math.min(limit.value + pageSize, total.value);
    };

    const reset = () => {
        limit.value = pageSize;
    };

    // Keep the limit sane when the underlying list changes (filters, deletes).
    watch(total, (count) => {
        if (limit.value > count) {
            limit.value = Math.max(pageSize, Math.min(limit.value, count));
        }

        if (count <= pageSize) {
            limit.value = pageSize;
        }
    });

    return { visibleItems, hasMore, remaining, total, loadMore, reset };
}
