<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Building2,
    CalendarDays,
    Check,
    Clock,
    MessageCircle,
    UserPlus,
    Users,
} from '@lucide/vue';
import { useT } from '@/composables/useT';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { login, register } from '@/routes';

const { t } = useT();

const features = [
    { key: 'events', icon: CalendarDays },
    { key: 'volunteers', icon: Users },
    { key: 'teams', icon: Building2 },
    { key: 'whatsapp', icon: MessageCircle },
];

const steps = [
    { key: 'one', icon: UserPlus },
    { key: 'two', icon: CalendarDays },
    { key: 'three', icon: MessageCircle },
];

const stats = ['time', 'whatsapp', 'mobile'] as const;

// Cartão-assinatura: linhas da escala "ao vivo".
const roster = [
    {
        role: 'reception',
        person: 'ana',
        tone: 'bg-rose-500',
        confirmed: true,
    },
    { role: 'sound', person: 'bruno', tone: 'bg-sky-500', confirmed: true },
    {
        role: 'cleaning',
        person: 'carla',
        tone: 'bg-violet-500',
        confirmed: true,
    },
    {
        role: 'parking',
        person: 'diego',
        tone: 'bg-amber-500',
        confirmed: false,
    },
];

function initial(name: string) {
    return name.charAt(0).toUpperCase();
}
</script>

<template>
    <PublicLayout
        :title="t('app.marketing.meta_title')"
        :description="t('app.marketing.meta_description')"
    >
        <!-- Hero -->
        <section class="relative overflow-hidden">
            <!-- ambient brand glow -->
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -top-32 left-1/2 -z-10 h-[420px] w-[720px] max-w-[130%] -translate-x-1/2 rounded-full bg-brand-soft-2 opacity-70 blur-3xl"
            />

            <div
                class="mx-auto grid w-full max-w-6xl items-center gap-12 px-4 pt-14 pb-16 sm:px-6 lg:grid-cols-[1.05fr_0.95fr] lg:gap-8 lg:pt-20 lg:pb-24"
            >
                <div class="text-center lg:text-left">
                    <span
                        class="inline-flex items-center gap-2 rounded-full border border-brand-soft-2 bg-brand-soft px-3 py-1 text-xs font-semibold text-brand-strong"
                    >
                        <span class="size-1.5 rounded-full bg-brand" />
                        {{ t('app.marketing.hero.eyebrow') }}
                    </span>

                    <h1
                        class="mt-5 text-4xl leading-[1.05] font-extrabold tracking-tight text-balance sm:text-5xl lg:text-6xl"
                    >
                        {{ t('app.marketing.hero.title') }}
                        <span class="text-brand">
                            {{ t('app.marketing.hero.title_highlight') }}
                        </span>
                    </h1>

                    <p
                        class="mx-auto mt-5 max-w-xl text-lg text-pretty text-muted-foreground lg:mx-0"
                    >
                        {{ t('app.marketing.hero.description') }}
                    </p>

                    <div
                        class="mt-8 flex flex-col items-stretch gap-3 sm:flex-row sm:items-center sm:justify-center lg:justify-start"
                    >
                        <Link
                            :href="register()"
                            class="group inline-flex h-12 items-center justify-center gap-2 rounded-full bg-brand px-6 text-base font-semibold text-white shadow-sm transition hover:bg-brand-strong focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
                        >
                            {{ t('app.marketing.hero.cta_primary') }}
                            <ArrowRight
                                class="size-4 transition-transform group-hover:translate-x-0.5"
                            />
                        </Link>
                        <Link
                            :href="login()"
                            class="inline-flex h-12 items-center justify-center rounded-full border border-border bg-background px-6 text-base font-semibold text-foreground transition hover:bg-muted focus-visible:ring-2 focus-visible:ring-brand focus-visible:outline-none"
                        >
                            {{ t('app.marketing.hero.cta_secondary') }}
                        </Link>
                    </div>

                    <p class="mt-4 text-sm text-muted-foreground">
                        {{ t('app.marketing.hero.note') }}
                    </p>
                </div>

                <!-- Signature: live roster card -->
                <div class="relative mx-auto w-full max-w-md lg:max-w-none">
                    <div
                        aria-hidden="true"
                        class="absolute -inset-3 -z-10 rounded-[28px] bg-brand-soft"
                    />
                    <div
                        class="rounded-3xl border border-border bg-card p-5 shadow-xl shadow-black/5 sm:p-6"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-base font-bold">
                                    {{ t('app.marketing.hero_card.label') }}
                                </p>
                                <p
                                    class="mt-0.5 flex items-center gap-1.5 text-sm text-muted-foreground"
                                >
                                    <Clock class="size-3.5" />
                                    {{ t('app.marketing.hero_card.date') }}
                                </p>
                            </div>
                            <span
                                class="inline-flex items-center gap-1 rounded-full bg-brand-soft px-2.5 py-1 text-xs font-semibold text-brand-strong"
                            >
                                <Check class="size-3.5" />
                                {{
                                    t(
                                        'app.marketing.hero_card.confirmed_summary',
                                    )
                                }}
                            </span>
                        </div>

                        <ul class="mt-5 space-y-2.5">
                            <li
                                v-for="row in roster"
                                :key="row.role"
                                class="flex items-center gap-3 rounded-2xl border border-border/70 bg-background p-3"
                            >
                                <span
                                    class="flex size-9 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white"
                                    :class="row.tone"
                                >
                                    {{
                                        initial(
                                            t(
                                                `app.marketing.hero_card.people.${row.person}`,
                                            ),
                                        )
                                    }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold">
                                        {{
                                            t(
                                                `app.marketing.hero_card.people.${row.person}`,
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{
                                            t(
                                                `app.marketing.hero_card.roles.${row.role}`,
                                            )
                                        }}
                                    </p>
                                </div>
                                <span
                                    v-if="row.confirmed"
                                    class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400"
                                >
                                    <Check class="size-3" />
                                    {{
                                        t(
                                            'app.marketing.hero_card.status_confirmed',
                                        )
                                    }}
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2.5 py-1 text-xs font-semibold text-amber-600 dark:text-amber-400"
                                >
                                    <Clock class="size-3" />
                                    {{
                                        t(
                                            'app.marketing.hero_card.status_pending',
                                        )
                                    }}
                                </span>
                            </li>
                        </ul>

                        <div
                            class="mt-4 flex items-center gap-2 rounded-2xl bg-brand-soft px-3 py-2.5 text-xs font-medium text-brand-strong"
                        >
                            <MessageCircle class="size-4 shrink-0" />
                            {{ t('app.marketing.hero_card.whatsapp_hint') }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats -->
        <section class="border-y border-border/70 bg-muted/30">
            <div
                class="mx-auto grid w-full max-w-6xl grid-cols-1 divide-y divide-border/70 px-4 sm:grid-cols-3 sm:divide-x sm:divide-y-0 sm:px-6"
            >
                <div
                    v-for="key in stats"
                    :key="key"
                    class="flex flex-col items-center gap-0.5 py-6 text-center sm:py-8"
                >
                    <span
                        class="text-2xl font-extrabold tracking-tight text-brand sm:text-3xl"
                    >
                        {{ t(`app.marketing.stats.items.${key}.value`) }}
                    </span>
                    <span class="text-sm text-muted-foreground">
                        {{ t(`app.marketing.stats.items.${key}.label`) }}
                    </span>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section
            id="recursos"
            class="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 lg:py-24"
        >
            <div class="mx-auto max-w-2xl text-center">
                <p
                    class="text-sm font-semibold tracking-wider text-brand uppercase"
                >
                    {{ t('app.marketing.features.eyebrow') }}
                </p>
                <h2
                    class="mt-2 text-3xl font-extrabold tracking-tight text-balance sm:text-4xl"
                >
                    {{ t('app.marketing.features.title') }}
                </h2>
                <p class="mt-3 text-lg text-pretty text-muted-foreground">
                    {{ t('app.marketing.features.subtitle') }}
                </p>
            </div>

            <div
                class="mt-12 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
            >
                <div
                    v-for="feature in features"
                    :key="feature.key"
                    class="rounded-3xl border border-border bg-card p-6 transition hover:border-brand-soft-2 hover:shadow-lg hover:shadow-black/5"
                >
                    <span
                        class="flex size-12 items-center justify-center rounded-2xl bg-brand-soft text-brand"
                    >
                        <component :is="feature.icon" class="size-6" />
                    </span>
                    <h3 class="mt-5 text-lg font-bold">
                        {{
                            t(
                                `app.marketing.features.items.${feature.key}.title`,
                            )
                        }}
                    </h3>
                    <p class="mt-2 text-sm text-pretty text-muted-foreground">
                        {{
                            t(
                                `app.marketing.features.items.${feature.key}.body`,
                            )
                        }}
                    </p>
                </div>
            </div>
        </section>

        <!-- Steps -->
        <section class="border-t border-border/70 bg-muted/30">
            <div class="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 lg:py-24">
                <div class="mx-auto max-w-2xl text-center">
                    <p
                        class="text-sm font-semibold tracking-wider text-brand uppercase"
                    >
                        {{ t('app.marketing.steps.eyebrow') }}
                    </p>
                    <h2
                        class="mt-2 text-3xl font-extrabold tracking-tight text-balance sm:text-4xl"
                    >
                        {{ t('app.marketing.steps.title') }}
                    </h2>
                    <p class="mt-3 text-lg text-pretty text-muted-foreground">
                        {{ t('app.marketing.steps.subtitle') }}
                    </p>
                </div>

                <ol class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <li
                        v-for="(step, index) in steps"
                        :key="step.key"
                        class="relative rounded-3xl border border-border bg-card p-6"
                    >
                        <div class="flex items-center justify-between">
                            <span
                                class="flex size-12 items-center justify-center rounded-2xl bg-brand text-white"
                            >
                                <component :is="step.icon" class="size-6" />
                            </span>
                            <span
                                class="text-5xl font-black text-brand-soft-2"
                            >
                                {{ index + 1 }}
                            </span>
                        </div>
                        <h3 class="mt-5 text-lg font-bold">
                            {{
                                t(`app.marketing.steps.items.${step.key}.title`)
                            }}
                        </h3>
                        <p
                            class="mt-2 text-sm text-pretty text-muted-foreground"
                        >
                            {{
                                t(`app.marketing.steps.items.${step.key}.body`)
                            }}
                        </p>
                    </li>
                </ol>
            </div>
        </section>

        <!-- CTA -->
        <section class="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 lg:py-24">
            <div
                class="relative overflow-hidden rounded-[32px] bg-brand-ink px-6 py-14 text-center sm:px-12"
            >
                <div
                    aria-hidden="true"
                    class="pointer-events-none absolute -top-24 right-0 h-64 w-64 rounded-full bg-brand opacity-25 blur-3xl"
                />
                <div
                    aria-hidden="true"
                    class="pointer-events-none absolute -bottom-24 left-0 h-64 w-64 rounded-full bg-brand-accent opacity-20 blur-3xl"
                />
                <h2
                    class="relative mx-auto max-w-2xl text-3xl font-extrabold tracking-tight text-balance text-white sm:text-4xl"
                >
                    {{ t('app.marketing.cta.title') }}
                </h2>
                <p
                    class="relative mx-auto mt-4 max-w-xl text-lg text-pretty text-white/70"
                >
                    {{ t('app.marketing.cta.subtitle') }}
                </p>
                <div
                    class="relative mt-8 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center"
                >
                    <Link
                        :href="register()"
                        class="group inline-flex h-12 items-center justify-center gap-2 rounded-full bg-white px-7 text-base font-semibold text-brand-ink shadow-sm transition hover:bg-white/90 focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-brand-ink focus-visible:outline-none"
                    >
                        {{ t('app.marketing.cta.button') }}
                        <ArrowRight
                            class="size-4 transition-transform group-hover:translate-x-0.5"
                        />
                    </Link>
                    <Link
                        :href="login()"
                        class="inline-flex h-12 items-center justify-center rounded-full border border-white/25 px-7 text-base font-semibold text-white transition hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
                    >
                        {{ t('app.marketing.cta.secondary') }}
                    </Link>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
