<script setup lang="ts">
import { ArrowUp } from '@lucide/vue';
import { onMounted, onUnmounted, ref } from 'vue';
import { useT } from '@/composables/useT';

const { t } = useT();

/** Pixels scrolled before the button reveals itself. */
const THRESHOLD = 400;

const visible = ref(false);

const onScroll = () => {
    visible.value = window.scrollY > THRESHOLD;
};

const scrollToTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

onMounted(() => {
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
});

onUnmounted(() => {
    window.removeEventListener('scroll', onScroll);
});
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-2 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-2 opacity-0"
    >
        <button
            v-show="visible"
            type="button"
            data-test="scroll-to-top"
            :aria-label="t('app.common.scroll_to_top')"
            :title="t('app.common.scroll_to_top')"
            class="fixed right-4 bottom-20 z-30 flex size-10 items-center justify-center rounded-full border border-border bg-background/90 text-muted-foreground shadow-md backdrop-blur-md transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-brand focus-visible:outline-none lg:right-6 lg:bottom-6"
            @click="scrollToTop"
        >
            <ArrowUp class="size-5" />
        </button>
    </Transition>
</template>
