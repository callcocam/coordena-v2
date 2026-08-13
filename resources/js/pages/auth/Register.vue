<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TeamInvitationAlert from '@/components/TeamInvitationAlert.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/composables/useT';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';
import type { TeamInvitationContext } from '@/types';

defineProps<{
    passwordRules: string;
    teamInvitation?: TeamInvitationContext | null;
}>();

defineOptions({
    layout: [
        AuthLayout,
        {
            title: 'app.auth.register.title',
            description: 'app.auth.register.description',
        },
    ],
});

const { t } = useT();
</script>

<template>
    <Head :title="t('app.auth.register.meta_title')" />

    <TeamInvitationAlert
        v-if="teamInvitation"
        :invitation="teamInvitation"
        :action="t('app.auth.register.invitation_action')"
    />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name">{{ t('app.auth.fields.name') }}</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    :placeholder="t('app.auth.fields.name_placeholder')"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">{{ t('app.auth.fields.email') }}</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    :placeholder="t('app.auth.fields.email_placeholder')"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">{{ t('app.auth.fields.password') }}</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password"
                    :placeholder="t('app.auth.fields.password_placeholder')"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">{{
                    t('app.auth.fields.password_confirmation')
                }}</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    :placeholder="t('app.auth.fields.password_confirmation_placeholder')"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                {{ t('app.auth.register.submit') }}
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            {{ t('app.auth.register.has_account') }}
            <TextLink
                :href="
                    teamInvitation
                        ? login.url({
                              query: {
                                  invitation: teamInvitation.code,
                              },
                          })
                        : login()
                "
                class="underline underline-offset-4"
                :tabindex="6"
                data-test="team-invitation-login-link"
            >
                {{ t('app.auth.register.login_link') }}
            </TextLink>
        </div>
    </Form>
</template>
