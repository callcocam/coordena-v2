<script setup lang="ts">
import { useT } from '@/composables/useT';
import PublicLayout from '@/layouts/PublicLayout.vue';

const { t } = useT();

const sectionKeys = [
    'what',
    'respond',
    'after',
    'deadline',
    'contact',
] as const;
</script>

<template>
    <PublicLayout
        :title="t('app.public_talks.help.title')"
        :description="t('app.public_talks.help.description')"
    >
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-8 px-4 py-12">
            <div class="flex flex-col gap-2">
                <h1 class="text-2xl font-semibold">
                    {{ t('app.public_talks.help.title') }}
                </h1>
                <p class="text-muted-foreground">
                    {{ t('app.public_talks.help.description') }}
                </p>
            </div>

            <section
                v-for="key in sectionKeys"
                :key="key"
                class="flex flex-col gap-2"
                :data-test="`help-section-${key}`"
            >
                <h2 class="text-lg font-semibold">
                    {{ t(`app.public_talks.help.sections.${key}.title`) }}
                </h2>
                <p class="text-sm leading-relaxed text-muted-foreground">
                    {{ t(`app.public_talks.help.sections.${key}.body`) }}
                </p>
            </section>
        </div>
    </PublicLayout>
</template>
