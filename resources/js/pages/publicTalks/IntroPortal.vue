<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CalendarDays } from '@lucide/vue';
import { useT } from '@/composables/useT';

type Props = {
    homeCongregation: string;
    city: string | null;
    meetingWeekday: number | null;
    meetingTime: string | null;
    coordinator: { name: string; phone: string | null } | null;
    weeks: {
        id: string;
        date: string;
        speaker: string | null;
        outline: string | null;
    }[];
};

const props = defineProps<Props>();

const { t } = useT();

const title = (): string => {
    const home =
        props.city !== null ? `${props.homeCongregation} (${props.city})` : props.homeCongregation;

    return t('app.public_talks.intro.portal.title', { home });
};

const dateLabel = (date: string): string =>
    new Date(`${date}T12:00:00`).toLocaleDateString('pt-BR', {
        weekday: 'short',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
</script>

<template>
    <Head :title="title()" />

    <div class="mx-auto flex min-h-svh w-full max-w-2xl flex-col gap-6 p-4 py-10">
        <div class="flex flex-col gap-2 text-center">
            <h1 class="text-xl font-semibold">{{ title() }}</h1>
            <p class="text-sm text-muted-foreground">
                {{ t('app.public_talks.intro.portal.description') }}
            </p>
            <p
                v-if="props.meetingWeekday !== null && props.meetingTime !== null"
                class="text-sm text-muted-foreground"
            >
                {{
                    t('app.public_talks.intro.portal.meeting', {
                        weekday: t(`app.public_talks.weekdays.${props.meetingWeekday}`),
                        time: props.meetingTime,
                    })
                }}
            </p>
            <p v-if="props.coordinator !== null" class="text-sm text-muted-foreground">
                {{
                    t('app.public_talks.intro.portal.coordinator', {
                        name: props.coordinator.name,
                        phone: props.coordinator.phone ?? '',
                    })
                }}
            </p>
        </div>

        <div class="flex flex-col gap-2 rounded-lg border p-4">
            <h2 class="flex items-center gap-2 text-sm font-medium">
                <CalendarDays class="size-4" />
                {{ t('app.public_talks.intro.portal.upcoming') }}
            </h2>

            <p
                v-if="props.weeks.length === 0"
                class="py-4 text-center text-sm text-muted-foreground"
                data-test="intro-portal-empty"
            >
                {{ t('app.public_talks.intro.portal.empty') }}
            </p>

            <ul v-else class="divide-y" data-test="intro-portal-weeks">
                <li
                    v-for="week in props.weeks"
                    :key="week.id"
                    class="flex flex-col gap-0.5 py-2 text-sm"
                >
                    <span class="font-medium">{{ dateLabel(week.date) }}</span>
                    <span class="text-muted-foreground">
                        {{ week.outline ?? t('app.public_talks.intro.portal.to_define') }}
                        <template v-if="week.speaker"> · {{ week.speaker }}</template>
                    </span>
                </li>
            </ul>
        </div>
    </div>
</template>
