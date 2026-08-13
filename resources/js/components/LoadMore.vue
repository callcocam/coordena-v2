<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';

withDefaults(
    defineProps<{
        /** Whether there are more items to reveal. */
        hasMore: boolean;
        /** How many items remain hidden. */
        remaining?: number;
    }>(),
    {
        remaining: 0,
    },
);

const emit = defineEmits<{
    (e: 'load-more'): void;
}>();

const { t } = useT();
</script>

<template>
    <div v-if="hasMore" class="flex justify-center pt-2">
        <Button
            variant="outline"
            size="sm"
            data-test="load-more-button"
            @click="emit('load-more')"
        >
            <ChevronDown class="h-4 w-4" />
            <span>
                {{
                    remaining > 0
                        ? t('app.common.load_more.count', { count: remaining })
                        : t('app.common.load_more.label')
                }}
            </span>
        </Button>
    </div>
</template>
