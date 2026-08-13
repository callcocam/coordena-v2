<script setup lang="ts">
import LegalDocument from '@/components/public/LegalDocument.vue';
import { useT } from '@/composables/useT';
import PublicLayout from '@/layouts/PublicLayout.vue';

const { t } = useT();
</script>

<template>
    <PublicLayout
        :title="t('app.legal.privacy.meta_title')"
        :description="t('app.legal.privacy.meta_description')"
    >
        <LegalDocument doc-key="privacy" />
    </PublicLayout>
</template>
