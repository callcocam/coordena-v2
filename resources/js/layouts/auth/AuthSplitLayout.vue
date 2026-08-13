<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, Check } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { useT } from '@/composables/useT';
import { home } from '@/routes';

const { title = '', description = '' } = defineProps<{
    title?: string;
    description?: string;
}>();

const { t } = useT();

// Assinatura: a "escala viva". Os voluntários assentam nas vagas e confirmam
// um a um no carregamento. Estado final é o padrão (reduced-motion/SSR).
const roster = [
    { role: 'reception', person: 'ana', tone: 'bg-rose-400' },
    { role: 'sound', person: 'bruno', tone: 'bg-sky-400' },
    { role: 'welcome', person: 'carla', tone: 'bg-amber-400' },
];

function initial(name: string): string {
    return name.charAt(0).toUpperCase();
}
</script>

<template>
    <div class="relative w-full lg:grid lg:min-h-dvh lg:grid-cols-2">
        <!-- Painel de marca (somente telas grandes): capa imersiva sempre escura -->
        <aside
            class="auth-brand-panel relative hidden overflow-hidden p-10 text-white lg:flex lg:flex-col xl:p-14"
        >
            <!-- glows da marca -->
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -top-24 -right-16 h-72 w-72 rounded-full bg-brand opacity-25 blur-3xl"
            />
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -bottom-24 -left-16 h-72 w-72 rounded-full bg-brand-accent opacity-20 blur-3xl"
            />

            <!-- Lockup branco da marca (variante monocromática para o painel escuro) -->
            <Link
                :href="home()"
                class="relative z-10 inline-flex w-fit items-center gap-2.5 rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-white/70"
                aria-label="Coordena"
            >
                <span
                    class="flex size-9 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15"
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
                            stroke="#fff"
                            stroke-width="1.2"
                        />
                    </svg>
                </span>
                <span class="text-lg font-extrabold tracking-tight text-white">
                    Coordena
                </span>
            </Link>

            <!-- Tese + assinatura -->
            <div
                class="relative z-10 flex flex-1 flex-col justify-center py-12"
            >
                <div class="max-w-md">
                    <h2
                        class="text-3xl font-extrabold tracking-tight text-balance xl:text-4xl"
                    >
                        {{ t('app.auth.common.brand_title') }}
                    </h2>
                    <p class="mt-4 text-base text-pretty text-white/70">
                        {{ t('app.auth.common.brand_tagline') }}
                    </p>

                    <!-- Escala viva -->
                    <div
                        class="mt-9 w-full rounded-2xl border border-white/10 bg-white/[0.06] p-4 backdrop-blur-sm"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p
                                    class="truncate text-sm font-semibold text-white"
                                >
                                    {{ t('app.auth.common.panel.label') }}
                                </p>
                                <p
                                    class="mt-0.5 flex items-center gap-1.5 text-xs text-white/55"
                                >
                                    <span
                                        class="live-dot size-1.5 rounded-full bg-brand-accent"
                                    />
                                    {{ t('app.auth.common.panel.meta') }}
                                </p>
                            </div>
                            <span
                                class="shrink-0 rounded-full bg-white/10 px-2.5 py-1 text-xs font-semibold text-white/80"
                            >
                                3&nbsp;/&nbsp;3
                            </span>
                        </div>

                        <ul class="mt-4 space-y-2">
                            <li
                                v-for="(row, index) in roster"
                                :key="row.role"
                                class="roster-row flex items-center gap-3 rounded-xl bg-white/[0.05] p-2.5"
                                :style="{ '--i': index }"
                            >
                                <span
                                    class="flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                                    :class="row.tone"
                                >
                                    {{
                                        initial(
                                            t(
                                                `app.auth.common.panel.people.${row.person}`,
                                            ),
                                        )
                                    }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="truncate text-sm font-medium text-white"
                                    >
                                        {{
                                            t(
                                                `app.auth.common.panel.people.${row.person}`,
                                            )
                                        }}
                                    </p>
                                    <p class="truncate text-xs text-white/50">
                                        {{
                                            t(
                                                `app.auth.common.panel.roles.${row.role}`,
                                            )
                                        }}
                                    </p>
                                </div>
                                <span
                                    class="roster-check inline-flex items-center gap-1 rounded-full bg-emerald-400/15 px-2 py-1 text-xs font-semibold text-emerald-300"
                                    :style="{ '--i': index }"
                                >
                                    <Check class="size-3" />
                                    {{ t('app.auth.common.panel.confirmed') }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Lado do formulário -->
        <div
            class="relative flex min-h-dvh flex-col px-6 py-8 sm:px-8 lg:min-h-0"
        >
            <!-- Topo: marca (mobile) + toggle de tema -->
            <div class="flex items-center justify-between">
                <Link
                    :href="home()"
                    class="inline-flex items-center gap-2 rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-brand lg:hidden"
                    aria-label="Coordena"
                >
                    <AppLogoIcon class="size-8 rounded-md shadow-sm" />
                    <span class="text-lg font-extrabold tracking-tight">
                        Coordena
                    </span>
                </Link>
                <ThemeToggle
                    class="ml-auto"
                    label="app.auth.common.theme_toggle"
                />
            </div>

            <div class="flex flex-1 flex-col justify-center py-8">
                <div class="mx-auto w-full max-w-md">
                    <div class="text-center lg:text-left">
                        <h1
                            v-if="title"
                            class="text-2xl font-extrabold tracking-tight text-balance"
                        >
                            {{ t(title) }}
                        </h1>
                        <p
                            v-if="description"
                            class="mt-2 text-sm text-pretty text-muted-foreground"
                        >
                            {{ t(description) }}
                        </p>
                    </div>

                    <div
                        class="mt-8 rounded-3xl border border-border bg-card p-6 shadow-xl shadow-black/5 sm:p-8"
                    >
                        <slot />
                    </div>

                    <div class="mt-6 text-center lg:text-left">
                        <Link
                            :href="home()"
                            class="inline-flex items-center gap-1.5 rounded-lg text-sm font-medium text-muted-foreground transition hover:text-brand focus-visible:ring-2 focus-visible:ring-brand focus-visible:outline-none"
                        >
                            <ArrowLeft class="size-4" />
                            {{ t('app.auth.common.back_home') }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.auth-brand-panel {
    background:
        radial-gradient(
            120% 120% at 12% 8%,
            rgba(45, 212, 191, 0.16),
            transparent 55%
        ),
        linear-gradient(160deg, #0b3d3a 0%, #042f2e 62%, #04211f 100%);
}

/* Sequência orquestrada de carregamento — só quando o usuário permite movimento.
   Estado final (visível) é o padrão, garantindo reduced-motion e SSR corretos. */
@media (prefers-reduced-motion: no-preference) {
    .roster-row {
        opacity: 0;
        transform: translateY(10px);
        animation: auth-row-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        animation-delay: calc(0.2s + var(--i) * 0.14s);
    }

    .roster-check {
        opacity: 0;
        transform: scale(0.6);
        animation: auth-check-in 0.4s ease forwards;
        animation-delay: calc(0.55s + var(--i) * 0.14s);
    }

    .live-dot {
        animation: auth-live-pulse 2.4s ease-in-out infinite;
    }
}

@keyframes auth-row-in {
    to {
        opacity: 1;
        transform: none;
    }
}

@keyframes auth-check-in {
    to {
        opacity: 1;
        transform: none;
    }
}

@keyframes auth-live-pulse {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.35;
    }
}
</style>
