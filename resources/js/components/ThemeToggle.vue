<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { useT } from '@/composables/useT';

const { label = 'app.common.theme_toggle' } = defineProps<{
    label?: string;
}>();

const { t } = useT();
const { resolvedAppearance, updateAppearance } = useAppearance();

const isDark = computed(() => resolvedAppearance.value === 'dark');

function toggle() {
    updateAppearance(isDark.value ? 'light' : 'dark');
}
</script>

<template>
    <button
        type="button"
        class="inline-flex size-10 items-center justify-center rounded-full text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-brand focus-visible:outline-none"
        :aria-label="t(label)"
        @click="toggle"
    >
        <Sun v-if="isDark" class="size-5" />
        <Moon v-else class="size-5" />
    </button>
</template>
