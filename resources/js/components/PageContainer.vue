<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';

type Props = {
    title?: string;
    description?: string;
    backHref?: NonNullable<InertiaLinkProps['href']>;
    width?: 'xl' | '3xl' | '5xl' | 'full';
};

const props = withDefaults(defineProps<Props>(), {
    width: '3xl',
});

const widthClasses: Record<NonNullable<Props['width']>, string> = {
    xl: 'max-w-xl',
    '3xl': 'max-w-3xl',
    '5xl': 'max-w-5xl',
    full: 'max-w-full',
};

const { t } = useT();
</script>

<template>
    <div
        :class="[
            'mx-auto flex w-full flex-col gap-6 p-4 sm:p-6',
            widthClasses[props.width],
        ]"
    >
        <div
            v-if="title || backHref || $slots.heading || $slots.actions"
            class="flex flex-wrap items-start gap-2"
        >
            <Button
                v-if="backHref"
                variant="ghost"
                size="icon"
                class="shrink-0"
                as-child
                data-test="back-button"
            >
                <Link :href="backHref">
                    <ArrowLeft class="size-5" />
                    <span class="sr-only">{{ t('app.common.back') }}</span>
                </Link>
            </Button>
            <div class="min-w-0 flex-1">
                <slot name="heading">
                    <Heading
                        v-if="title"
                        :title="title"
                        :description="description"
                    />
                </slot>
            </div>
            <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
                <slot name="actions" />
            </div>
        </div>
        <slot />
    </div>
</template>
