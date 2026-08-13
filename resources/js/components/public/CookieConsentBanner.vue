<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';

const { t } = useT();

// Bump when the cookie notice text changes so it's shown again even to
// visitors who already dismissed an older version.
const NOTICE_VERSION = '2026-07-10';
const STORAGE_KEY = 'coordena_cookie_notice';

const dismissed = ref(
    typeof window !== 'undefined' &&
        window.localStorage.getItem(STORAGE_KEY) === NOTICE_VERSION,
);

function dismiss() {
    window.localStorage.setItem(STORAGE_KEY, NOTICE_VERSION);
    dismissed.value = true;
}
</script>

<template>
    <div
        v-if="!dismissed"
        class="fixed inset-x-0 bottom-0 z-50 border-t bg-background/95 p-4 text-sm shadow-lg backdrop-blur"
        data-test="cookie-consent-banner"
    >
        <div
            class="mx-auto flex max-w-3xl flex-col items-center gap-3 sm:flex-row sm:justify-between"
        >
            <p class="text-center text-muted-foreground sm:text-left">
                {{ t('app.lgpd.cookies.notice') }}
                <Link href="/privacidade" class="underline">
                    {{ t('app.lgpd.cookies.link') }}
                </Link>
            </p>
            <Button
                type="button"
                size="sm"
                data-test="cookie-consent-accept"
                @click="dismiss"
            >
                {{ t('app.lgpd.cookies.accept') }}
            </Button>
        </div>
    </div>
</template>
