<script setup lang="ts">
import LegalDocument from '@/components/public/LegalDocument.vue';
import { useT } from '@/composables/useT';
import PublicLayout from '@/layouts/PublicLayout.vue';

const { t } = useT();
</script>

<template>
    <PublicLayout
        :title="t('app.legal.terms.meta_title')"
        :description="t('app.legal.terms.meta_description')"
    >
        <LegalDocument doc-key="terms" />
    </PublicLayout>
</template>
