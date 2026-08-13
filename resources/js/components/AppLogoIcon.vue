<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 32 32"
        fill="none"
        :class="className"
        v-bind="$attrs"
    >
        <rect width="32" height="32" rx="7" fill="#0d9488" />
        <g
            stroke="#ffffff"
            stroke-width="2"
            stroke-linecap="round"
            opacity="0.7"
        >
            <path d="M16 15 L9.5 10" />
            <path d="M16 15 L22.5 10" />
            <path d="M16 15 L16 23.5" />
        </g>
        <circle cx="9.5" cy="10" r="3" fill="#ffffff" />
        <circle cx="22.5" cy="10" r="3" fill="#ffffff" />
        <circle cx="16" cy="23.5" r="3" fill="#ffffff" />
        <circle
            cx="16"
            cy="15"
            r="3.6"
            fill="#f59e0b"
            stroke="#ffffff"
            stroke-width="1.4"
        />
    </svg>
</template>
