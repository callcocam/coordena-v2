<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: [
        [
            AppLayout,
            {
                breadcrumbs: [
                    {
                        title: 'app.settings.appearance.breadcrumb',
                        href: edit(),
                    },
                ],
            },
        ],
        SettingsLayout,
    ],
});

const { t } = useT();
</script>

<template>
    <Head :title="t('app.settings.appearance.meta_title')" />

    <h1 class="sr-only">{{ t('app.settings.appearance.sr_title') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="app.settings.appearance.heading"
            description="app.settings.appearance.heading_desc"
        />
        <AppearanceTabs />
    </div>
</template>
