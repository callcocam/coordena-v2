<script setup lang="ts">
import { computed } from 'vue';
import { useT } from '@/composables/useT';

type Props = {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
};

const props = withDefaults(defineProps<Props>(), {
    variant: 'default',
});

const { t } = useT();

const resolvedTitle = computed<string>(() =>
    props.title ? t(props.title) : '',
);
const resolvedDescription = computed<string>(() =>
    props.description ? t(props.description) : '',
);
</script>

<template>
    <header :class="variant === 'small' ? '' : 'mb-8 space-y-0.5'">
        <h2
            :class="
                variant === 'small'
                    ? 'mb-0.5 text-base font-medium'
                    : 'text-xl font-semibold tracking-tight'
            "
        >
            {{ resolvedTitle }}
        </h2>
        <p v-if="resolvedDescription" class="text-sm text-muted-foreground">
            {{ resolvedDescription }}
        </p>
    </header>
</template>
