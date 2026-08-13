<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ListTree } from '@lucide/vue';
import { computed } from 'vue';
import { useT } from '@/composables/useT';

type Section = {
    id: string;
    title: string;
    body?: string[];
    list?: string[];
};

const props = defineProps<{
    /** Which document under app.legal.* to render: 'terms' | 'privacy'. */
    docKey: 'terms' | 'privacy';
}>();

const { t } = useT();
const page = usePage();

const doc = computed<Record<string, unknown>>(() => {
    const tree = page.props.translations as unknown as {
        app?: { legal?: Record<string, Record<string, unknown>> };
    };

    return tree?.app?.legal?.[props.docKey] ?? {};
});

const sections = computed<Section[]>(
    () => (doc.value.sections as Section[]) ?? [],
);

const readingMinutes = computed(() => {
    const words = sections.value.reduce((total, section) => {
        const parts = [...(section.body ?? []), ...(section.list ?? [])];

        return total + parts.join(' ').split(/\s+/).filter(Boolean).length;
    }, 0);

    return Math.max(1, Math.round(words / 200));
});
</script>

<template>
    <article class="mx-auto w-full max-w-6xl px-4 sm:px-6">
        <!-- Header -->
        <header class="border-b border-border/70 py-12 lg:py-16">
            <Link
                href="/"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground transition hover:text-brand"
            >
                <ArrowLeft class="size-4" />
                {{ t('app.legal.common.back_home') }}
            </Link>

            <p
                class="mt-6 text-sm font-semibold tracking-wider text-brand uppercase"
            >
                {{ t(`app.legal.${docKey}.eyebrow`) }}
            </p>
            <h1
                class="mt-2 text-3xl font-extrabold tracking-tight text-balance sm:text-4xl lg:text-5xl"
            >
                {{ t(`app.legal.${docKey}.title`) }}
            </h1>
            <p class="mt-4 max-w-2xl text-lg text-pretty text-muted-foreground">
                {{ t(`app.legal.${docKey}.intro`) }}
            </p>

            <div
                class="mt-6 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground"
            >
                <span>
                    {{ t('app.legal.common.updated_label') }}
                    <span class="font-medium text-foreground">
                        {{ t('app.legal.common.updated_at') }}
                    </span>
                </span>
                <span aria-hidden="true" class="hidden sm:inline">·</span>
                <span>
                    {{
                        t('app.legal.common.reading_note', {
                            minutes: readingMinutes,
                        })
                    }}
                </span>
            </div>
        </header>

        <div class="gap-12 py-10 lg:grid lg:grid-cols-[220px_1fr]">
            <!-- Table of contents -->
            <aside class="lg:sticky lg:top-24 lg:self-start">
                <details
                    class="rounded-2xl border border-border bg-card lg:border-0 lg:bg-transparent"
                >
                    <summary
                        class="flex cursor-pointer list-none items-center gap-2 px-4 py-3 text-sm font-semibold lg:cursor-default lg:px-0 lg:py-0 lg:text-xs lg:tracking-wider lg:text-muted-foreground lg:uppercase"
                    >
                        <ListTree class="size-4 lg:hidden" />
                        {{ t('app.legal.common.toc_title') }}
                    </summary>
                    <nav class="px-2 pb-3 lg:mt-4 lg:px-0 lg:pb-0">
                        <ul class="space-y-1">
                            <li v-for="section in sections" :key="section.id">
                                <a
                                    :href="`#${section.id}`"
                                    class="block rounded-lg px-2 py-1.5 text-sm text-muted-foreground transition hover:bg-muted hover:text-brand lg:px-2"
                                >
                                    {{ section.title }}
                                </a>
                            </li>
                        </ul>
                    </nav>
                </details>
            </aside>

            <!-- Sections -->
            <div class="min-w-0 space-y-10">
                <section
                    v-for="section in sections"
                    :id="section.id"
                    :key="section.id"
                    class="scroll-mt-24"
                >
                    <h2
                        class="text-xl font-bold tracking-tight text-foreground sm:text-2xl"
                    >
                        {{ section.title }}
                    </h2>
                    <div class="mt-3 space-y-3">
                        <p
                            v-for="(paragraph, i) in section.body ?? []"
                            :key="i"
                            class="text-base leading-relaxed text-pretty text-muted-foreground"
                        >
                            {{ paragraph }}
                        </p>
                    </div>
                    <ul v-if="section.list?.length" class="mt-3 space-y-2 pl-1">
                        <li
                            v-for="(item, i) in section.list"
                            :key="i"
                            class="flex gap-3 text-base leading-relaxed text-pretty text-muted-foreground"
                        >
                            <span
                                aria-hidden="true"
                                class="mt-2.5 size-1.5 shrink-0 rounded-full bg-brand"
                            />
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </article>
</template>
