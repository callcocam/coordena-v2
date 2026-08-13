<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/composables/useT';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: [
        AuthLayout,
        {
            title: 'app.auth.forgot_password.title',
            description: 'app.auth.forgot_password.description',
        },
    ],
});

defineProps<{
    status?: string;
}>();

const { t } = useT();
</script>

<template>
    <Head :title="t('app.auth.forgot_password.meta_title')" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <div class="space-y-6">
        <Form v-bind="email.form()" v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <Label for="email">{{ t('app.auth.fields.email') }}</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="off"
                    autofocus
                    :placeholder="t('app.auth.fields.email_placeholder')"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="my-6 flex items-center justify-start">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" />
                    {{ t('app.auth.forgot_password.submit') }}
                </Button>
            </div>
        </Form>

        <div class="space-x-1 text-center text-sm text-muted-foreground">
            <span>{{ t('app.auth.forgot_password.or_return') }}</span>
            <TextLink :href="login()">{{
                t('app.auth.forgot_password.login_link')
            }}</TextLink>
        </div>
    </div>
</template>
