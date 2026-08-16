<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PageContainer from '@/components/PageContainer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { schedule } from '@/routes/public-talks';
import { update as updateSettings } from '@/routes/public-talks/settings';

type SettingKey =
    | 'speaker_reminder_days'
    | 'speaker_second_reminder_days'
    | 'pending_alert_days'
    | 'exchange_nudge_days'
    | 'exchange_expire_days';

type Props = {
    settings: Record<SettingKey, number>;
    defaults: Record<SettingKey, number>;
    overrides: Partial<Record<SettingKey, number>>;
    canManage: boolean;
};

const props = defineProps<Props>();

defineOptions({
    layout: AppLayout,
});

const { t } = useT();
const page = usePage();

const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const errors = ref<Record<string, string>>({});
const processing = ref(false);

const form = ref<Record<SettingKey, string>>({
    speaker_reminder_days:
        props.overrides.speaker_reminder_days?.toString() ?? '',
    speaker_second_reminder_days:
        props.overrides.speaker_second_reminder_days?.toString() ?? '',
    pending_alert_days: props.overrides.pending_alert_days?.toString() ?? '',
    exchange_nudge_days: props.overrides.exchange_nudge_days?.toString() ?? '',
    exchange_expire_days:
        props.overrides.exchange_expire_days?.toString() ?? '',
});

const sections: { heading: string; description: string; keys: SettingKey[] }[] =
    [
        {
            heading: 'reminders_heading',
            description: 'reminders_description',
            keys: [
                'speaker_reminder_days',
                'speaker_second_reminder_days',
                'pending_alert_days',
            ],
        },
        {
            heading: 'exchange_heading',
            description: 'exchange_description',
            keys: ['exchange_nudge_days', 'exchange_expire_days'],
        },
    ];

const isCustomized = (key: SettingKey): boolean => form.value[key] !== '';

const submit = () => {
    const payload = Object.fromEntries(
        (Object.keys(form.value) as SettingKey[]).map((key) => [
            key,
            form.value[key] === '' ? null : Number(form.value[key]),
        ]),
    );

    router.put(updateSettings(teamSlug.value).url, payload, {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
        onError: (formErrors: Record<string, string>) =>
            (errors.value = formErrors),
        onSuccess: () => (errors.value = {}),
    });
};
</script>

<template>
    <Head :title="t('app.public_talks.settings.title')" />

    <PageContainer
        title="app.public_talks.settings.title"
        description="app.public_talks.settings.description"
        :back-href="schedule(teamSlug)"
    >
        <form class="space-y-8" @submit.prevent="submit">
            <section
                v-for="section in sections"
                :key="section.heading"
                class="space-y-4"
            >
                <div>
                    <h2 class="text-base font-semibold">
                        {{ t(`app.public_talks.settings.${section.heading}`) }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{
                            t(
                                `app.public_talks.settings.${section.description}`,
                            )
                        }}
                    </p>
                </div>

                <div
                    v-for="key in section.keys"
                    :key="key"
                    class="grid gap-2 sm:grid-cols-2 sm:items-start sm:gap-6"
                    :data-test="`setting-${key}`"
                >
                    <div class="space-y-1">
                        <Label :for="key" class="flex items-center gap-2">
                            {{ t(`app.public_talks.settings.${key}_label`) }}
                            <Badge
                                v-if="isCustomized(key)"
                                variant="secondary"
                                data-test="custom-badge"
                            >
                                {{
                                    t('app.public_talks.settings.custom_badge')
                                }}
                            </Badge>
                        </Label>
                        <p class="text-xs text-muted-foreground">
                            {{ t(`app.public_talks.settings.${key}_help`) }}
                        </p>
                    </div>
                    <div class="space-y-1">
                        <Input
                            :id="key"
                            v-model="form[key]"
                            type="number"
                            inputmode="numeric"
                            min="0"
                            :placeholder="String(props.defaults[key])"
                            :disabled="!props.canManage"
                            class="sm:max-w-32"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{
                                t('app.public_talks.settings.default_hint', {
                                    value: `${props.defaults[key]} ${t('app.public_talks.settings.days_unit')}`,
                                })
                            }}
                        </p>
                        <InputError :message="errors[key]" />
                    </div>
                </div>
            </section>

            <p
                v-if="!props.canManage"
                class="text-sm text-muted-foreground"
                data-test="read-only-note"
            >
                {{ t('app.public_talks.settings.read_only') }}
            </p>

            <div v-else class="flex justify-end">
                <Button
                    type="submit"
                    :disabled="processing"
                    data-test="save-settings"
                >
                    {{ t('app.public_talks.settings.save') }}
                </Button>
            </div>
        </form>
    </PageContainer>
</template>
