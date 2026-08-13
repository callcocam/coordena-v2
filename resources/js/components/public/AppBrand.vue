<script setup lang="ts">
withDefaults(
    defineProps<{
        /** Hide the wordmark, showing only the glyph. */
        iconOnly?: boolean;
    }>(),
    { iconOnly: false },
);
</script>

<template>
    <span class="inline-flex items-center gap-2.5">
        <!-- Signature glyph: three nodes coordinated around a center -->
        <span
            class="flex size-9 items-center justify-center rounded-xl bg-brand shadow-sm"
        >
            <svg
                viewBox="0 0 24 24"
                fill="none"
                class="size-5 text-white"
                aria-hidden="true"
            >
                <path
                    d="M12 12L6 7M12 12l6-5M12 12v6"
                    stroke="currentColor"
                    stroke-width="1.6"
                    stroke-linecap="round"
                    opacity="0.7"
                />
                <circle cx="6" cy="7" r="2.4" fill="currentColor" />
                <circle cx="18" cy="7" r="2.4" fill="currentColor" />
                <circle cx="12" cy="18" r="2.4" fill="currentColor" />
                <circle
                    cx="12"
                    cy="12"
                    r="2.8"
                    fill="var(--brand-accent)"
                    stroke="var(--brand)"
                    stroke-width="1.4"
                />
            </svg>
        </span>
        <span
            v-if="!iconOnly"
            class="text-lg font-extrabold tracking-tight text-foreground"
        >
            Coordena
        </span>
    </span>
</template>
