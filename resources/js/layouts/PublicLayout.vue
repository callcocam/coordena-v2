<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppBrand from '@/components/public/AppBrand.vue';
import CookieConsentBanner from '@/components/public/CookieConsentBanner.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { useT } from '@/composables/useT';
import { dashboard, login, register } from '@/routes';

const { title = '', description = '' } = defineProps<{
    title?: string;
    description?: string;
}>();

const { t } = useT();
const page = usePage();

const isAuthenticated = computed(() => Boolean(page.props.auth?.user));
const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);
</script>

<template>
    <Head :title="title">
        <meta v-if="description" name="description" :content="description" />
    </Head>

    <div
        class="coordena-public flex min-h-dvh flex-col bg-background text-foreground antialiased"
    >
        <a
            href="#conteudo"
            class="sr-only rounded-full bg-brand px-4 py-2 text-sm font-semibold text-white focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50"
        >
            {{ t('app.marketing.nav.skip') }}
        </a>

        <!-- Header -->
        <header
            class="sticky top-0 z-40 border-b border-border/70 bg-background/80 backdrop-blur-md"
        >
            <div
                class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between gap-3 px-4 sm:px-6"
            >
                <Link
                    href="/"
                    class="rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-brand"
                    aria-label="Coordena"
                >
                    <AppBrand />
                </Link>

                <div class="flex items-center gap-1.5 sm:gap-2">
                    <ThemeToggle />

                    <template v-if="isAuthenticated">
                        <Link
                            :href="dashboardUrl"
                            class="inline-flex h-10 items-center rounded-full bg-brand px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-strong focus-visible:ring-2 focus-visible:ring-brand focus-visible:outline-none"
                        >
                            {{ t('app.marketing.nav.dashboard') }}
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="hidden h-10 items-center rounded-full px-4 text-sm font-semibold text-foreground transition hover:bg-muted focus-visible:ring-2 focus-visible:ring-brand focus-visible:outline-none sm:inline-flex"
                        >
                            {{ t('app.marketing.nav.login') }}
                        </Link>
                        <Link
                            :href="register()"
                            class="inline-flex h-10 items-center rounded-full bg-brand px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-strong focus-visible:ring-2 focus-visible:ring-brand focus-visible:outline-none"
                        >
                            {{ t('app.marketing.nav.register') }}
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <!-- Content -->
        <main id="conteudo" class="flex-1">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="border-t border-border/70 bg-muted/30">
            <div class="mx-auto w-full max-w-6xl px-4 py-12 sm:px-6">
                <div
                    class="flex flex-col gap-10 sm:flex-row sm:justify-between"
                >
                    <div class="max-w-xs">
                        <AppBrand />
                        <p class="mt-3 text-sm text-muted-foreground">
                            {{ t('app.marketing.footer.tagline') }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-8 sm:gap-16">
                        <nav>
                            <h2
                                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                {{ t('app.marketing.footer.product_title') }}
                            </h2>
                            <ul class="mt-3 space-y-2 text-sm">
                                <li>
                                    <Link
                                        :href="register()"
                                        class="text-foreground/80 transition hover:text-brand"
                                    >
                                        {{ t('app.marketing.nav.register') }}
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        :href="login()"
                                        class="text-foreground/80 transition hover:text-brand"
                                    >
                                        {{ t('app.marketing.nav.login') }}
                                    </Link>
                                </li>
                            </ul>
                        </nav>

                        <nav>
                            <h2
                                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                {{ t('app.marketing.footer.legal_title') }}
                            </h2>
                            <ul class="mt-3 space-y-2 text-sm">
                                <li>
                                    <Link
                                        href="/termos"
                                        class="text-foreground/80 transition hover:text-brand"
                                    >
                                        {{ t('app.marketing.footer.terms') }}
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/privacidade"
                                        class="text-foreground/80 transition hover:text-brand"
                                    >
                                        {{ t('app.marketing.footer.privacy') }}
                                    </Link>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>

                <div
                    class="mt-10 flex flex-col gap-2 border-t border-border/70 pt-6 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between"
                >
                    <p>
                        © {{ new Date().getFullYear() }} Coordena.
                        {{ t('app.marketing.footer.rights') }}
                    </p>
                    <p>{{ t('app.marketing.footer.made_in') }}</p>
                </div>
            </div>
        </footer>

        <CookieConsentBanner />
    </div>
</template>
