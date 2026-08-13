<script setup lang="ts">
import { usePasskeyRegister } from '@laravel/passkeys/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';

const { t } = useT();

const emit = defineEmits<{
    success: [];
}>();

const getDefaultPasskeyName = () => {
    const ua = navigator.userAgent;

    const browser = [
        { pattern: /Edg|Edge/, name: 'Edge' },
        { pattern: /OPR|Opera|OPiOS/, name: 'Opera' },
        { pattern: /Firefox|FxiOS/, name: 'Firefox' },
        { pattern: /Chrome|CriOS/, name: 'Chrome' },
        { pattern: /Safari/, name: 'Safari' },
    ].find(({ pattern }) => pattern.test(ua))?.name;

    const os = [
        { pattern: /iPhone/, name: 'iPhone' },
        { pattern: /iPad|Macintosh(?=.*Mobile)/, name: 'iPad' },
        { pattern: /Android/, name: 'Android' },
        { pattern: /Mac/, name: 'Mac' },
        { pattern: /Windows/, name: 'Windows' },
    ].find(({ pattern }) => pattern.test(ua))?.name;

    return [browser, os].filter(Boolean).join(' on ') || '';
};

const name = ref(getDefaultPasskeyName());
const showForm = ref(false);

const { register, isLoading, error, isSupported } = usePasskeyRegister({
    onSuccess: () => {
        name.value = '';
        showForm.value = false;
        emit('success');
    },
});

const handleSubmit = async (event: Event) => {
    event.preventDefault();

    if (!name.value.trim()) {
        return;
    }

    await register(name.value);
};

const handleCancel = () => {
    showForm.value = false;
    name.value = '';
};
</script>

<template>
    <div v-if="!isSupported" class="text-sm text-muted-foreground">
        {{ t('app.settings.passkeys.register.not_supported') }}
    </div>

    <Button v-else-if="!showForm" variant="outline" @click="showForm = true">
        {{ t('app.settings.passkeys.register.add') }}
    </Button>

    <form
        v-else
        @submit="handleSubmit"
        class="space-y-4 rounded-lg border border-border bg-muted/50 p-4"
    >
        <div class="grid gap-2">
            <Label for="passkey-name">{{ t('app.settings.passkeys.register.name') }}</Label>
            <Input
                id="passkey-name"
                type="text"
                v-model="name"
                :placeholder="t('app.settings.passkeys.register.name_placeholder')"
                class="mt-1 block w-full border-foreground/20"
                autofocus
            />
            <p class="text-xs text-muted-foreground">
                {{ t('app.settings.passkeys.register.name_help') }}
            </p>
        </div>

        <InputError v-if="error" :message="error" />

        <div class="flex gap-2">
            <Button type="submit" :disabled="isLoading || !name.trim()">
                {{
                    isLoading
                        ? t('app.settings.passkeys.register.registering')
                        : t('app.settings.passkeys.register.register_button')
                }}
            </Button>
            <Button type="button" variant="ghost" @click="handleCancel">
                {{ t('app.settings.passkeys.register.cancel') }}
            </Button>
        </div>
    </form>
</template>
