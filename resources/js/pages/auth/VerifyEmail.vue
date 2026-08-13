<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/composables/useT';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: [
        AuthLayout,
        {
            title: 'app.auth.verify_email.title',
            description: 'app.auth.verify_email.description',
        },
    ],
});

defineProps<{
    status?: string;
}>();

const { t } = useT();
</script>

<template>
    <Head :title="t('app.auth.verify_email.meta_title')" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ t('app.auth.verify_email.sent') }}
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            {{ t('app.auth.verify_email.resend') }}
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            {{ t('app.auth.verify_email.logout') }}
        </TextLink>
    </Form>
</template>
