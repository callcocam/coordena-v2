<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { CalendarDays, Phone } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import OutlinePicker from '@/components/OutlinePicker.vue';
import PageContainer from '@/components/PageContainer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { schedule } from '@/routes/public-talks';
import { index as exchangeIndex } from '@/routes/public-talks/exchange';
import {
    notify as notifySlot,
    update as updateSlot,
} from '@/routes/public-talks/schedule';
import type {
    HomeCongregation,
    OutlineOption,
    ScheduleSpeaker,
    ScheduleWeek,
} from '@/types';

type Props = {
    month: string;
    months: { value: string }[];
    weeks: ScheduleWeek[];
    pendingCount: number;
    speakers: ScheduleSpeaker[];
    outlines: OutlineOption[];
    homeCongregation: HomeCongregation;
    canManage: boolean;
};

const props = defineProps<Props>();

defineOptions({
    layout: AppLayout,
});

const { t, locale } = useT();
const page = usePage();

const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const monthLabel = (value: string): string =>
    new Date(`${value}-01T12:00:00`).toLocaleDateString(
        locale.value.replace('_', '-'),
        { month: 'long', year: 'numeric' },
    );

const dateLabel = (value: string): string =>
    new Date(`${value}T12:00:00`).toLocaleDateString(
        locale.value.replace('_', '-'),
        { weekday: 'short', day: '2-digit', month: 'short' },
    );

const shortDateLabel = (value: string): string =>
    new Date(`${value}T12:00:00`).toLocaleDateString(
        locale.value.replace('_', '-'),
        { day: '2-digit', month: 'short' },
    );

const timeLabel = (value: string | null): string | null =>
    value ? value.slice(0, 5) : null;

/**
 * Concrete meeting moment of the week: home/incoming use the home
 * congregation's time; outgoing shows only the derived date.
 */
const meetingLabel = (week: ScheduleWeek): string => {
    const day = dateLabel(week.date);
    const time =
        week.type === 'outgoing'
            ? null
            : timeLabel(props.homeCongregation.meeting_time);

    return time ? `${day} · ${time}` : day;
};

const changeMonth = (value: unknown) => {
    if (typeof value !== 'string' || value === props.month) {
        return;
    }

    router.get(
        schedule(teamSlug.value).url,
        { month: value },
        { preserveState: false, preserveScroll: true },
    );
};

const statusVariant = (
    status: ScheduleWeek['status'],
): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'confirmed':
            return 'default';
        case 'needs_reschedule':
            return 'destructive';
        case 'open':
            return 'outline';
        default:
            return 'secondary';
    }
};

/* Bottom sheet state */
const sheetOpen = ref(false);
const activeWeek = ref<ScheduleWeek | null>(null);
const speakerId = ref<string | null>(null);
const outlineId = ref<string | null>(null);
const errors = ref<Record<string, string>>({});
const processing = ref(false);

const openWeek = (week: ScheduleWeek) => {
    if (!week.editable) {
        return;
    }

    activeWeek.value = week;
    speakerId.value = week.speaker?.id ?? null;
    outlineId.value = week.outline?.id ?? null;
    errors.value = {};
    sheetOpen.value = true;
};

const selectedSpeaker = computed<ScheduleSpeaker | null>(
    () =>
        props.speakers.find((speaker) => speaker.id === speakerId.value) ??
        null,
);

const isPrepared = (outline: OutlineOption): boolean =>
    selectedSpeaker.value?.outline_ids.includes(outline.id) ?? false;

const sortedOutlines = computed<OutlineOption[]>(() =>
    [...props.outlines].sort((a, b) => {
        const preparedDiff = Number(isPrepared(b)) - Number(isPrepared(a));

        return preparedDiff !== 0 ? preparedDiff : a.number - b.number;
    }),
);

const submit = (clear = false) => {
    if (!activeWeek.value) {
        return;
    }

    router.put(
        updateSlot([teamSlug.value, activeWeek.value.id]).url,
        clear
            ? { speaker_id: null, outline_id: null }
            : { speaker_id: speakerId.value, outline_id: outlineId.value },
        {
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
            onError: (formErrors) => (errors.value = formErrors),
            onSuccess: () => (sheetOpen.value = false),
        },
    );
};

const canNotify = computed(
    () =>
        activeWeek.value !== null &&
        activeWeek.value.speaker !== null &&
        activeWeek.value.outline !== null,
);

const notifySpeaker = () => {
    if (!activeWeek.value) {
        return;
    }

    router.post(
        notifySlot([teamSlug.value, activeWeek.value.id]).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
            onSuccess: () => (sheetOpen.value = false),
        },
    );
};

const goToExchange = () => {
    router.get(exchangeIndex(teamSlug.value).url, { month: props.month });
};
</script>

<template>
    <Head :title="t('app.public_talks.schedule.title')" />

    <PageContainer
        :title="t('app.public_talks.schedule.title')"
        :description="
            t('app.public_talks.schedule.description', {
                name: props.homeCongregation.name,
            })
        "
        :back-href="dashboard(teamSlug)"
        width="5xl"
    >
        <template #actions>
            <Button
                v-if="props.canManage"
                variant="outline"
                data-test="invite-congregation"
                @click="goToExchange"
            >
                {{ t('app.public_talks.schedule.invite_congregation') }}
            </Button>

            <Badge
                v-if="props.pendingCount > 0"
                variant="secondary"
                data-test="pending-count"
            >
                {{
                    t('app.public_talks.schedule.pending_badge', {
                        count: props.pendingCount,
                    })
                }}
            </Badge>

            <Select
                :model-value="props.month"
                @update:model-value="changeMonth"
            >
                <SelectTrigger class="w-48" data-test="month-select">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="option in props.months"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ monthLabel(option.value) }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </template>

        <div
            v-if="props.weeks.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            {{ t('app.public_talks.schedule.empty') }}
        </div>

        <div v-else class="space-y-3">
            <button
                v-for="week in props.weeks"
                :key="week.id"
                type="button"
                data-test="week-row"
                class="w-full rounded-lg border p-4 text-left transition-colors"
                :class="
                    week.editable
                        ? 'cursor-pointer hover:bg-accent'
                        : 'cursor-default'
                "
                @click="openWeek(week)"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <CalendarDays
                                class="size-4 shrink-0 text-muted-foreground"
                            />
                            <span class="font-medium">
                                {{
                                    t('app.public_talks.schedule.week_of', {
                                        date: shortDateLabel(week.week_start),
                                    })
                                }}
                            </span>
                            <Badge variant="outline">
                                {{
                                    t(
                                        `app.public_talks.schedule.types.${week.type}`,
                                    )
                                }}
                            </Badge>
                        </div>

                        <div
                            class="mt-1 text-xs text-muted-foreground capitalize"
                        >
                            {{ meetingLabel(week) }}
                        </div>

                        <div class="mt-2 text-sm">
                            <template v-if="week.type === 'outgoing'">
                                <span class="text-muted-foreground">
                                    {{ week.counterpart ?? '—' }}
                                </span>
                            </template>
                            <template v-else-if="week.speaker">
                                <span>{{ week.speaker.name }}</span>
                                <span
                                    v-if="week.outline"
                                    class="text-muted-foreground"
                                >
                                    — nº {{ week.outline.number }} ·
                                    {{ week.outline.title }}
                                </span>
                            </template>
                            <span v-else class="text-muted-foreground">
                                {{ t('app.public_talks.schedule.no_speaker') }}
                            </span>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-col items-end gap-2">
                        <Badge :variant="statusVariant(week.status)">
                            {{
                                t(
                                    `app.public_talks.schedule.statuses.${week.status}`,
                                )
                            }}
                        </Badge>

                        <span
                            v-if="week.editable"
                            class="inline-flex h-8 items-center rounded-md border px-3 text-xs font-medium"
                            data-test="week-action"
                        >
                            {{
                                week.speaker
                                    ? t('app.public_talks.schedule.edit_week')
                                    : t('app.public_talks.schedule.edit_slot')
                            }}
                        </span>
                    </div>
                </div>
            </button>
        </div>
    </PageContainer>

    <Sheet v-model:open="sheetOpen">
        <SheetContent side="bottom" class="mx-auto max-w-lg rounded-t-lg">
            <SheetHeader>
                <SheetTitle>
                    {{
                        t('app.public_talks.schedule.sheet_title', {
                            date: activeWeek ? dateLabel(activeWeek.date) : '',
                        })
                    }}
                </SheetTitle>
                <SheetDescription>
                    {{ t('app.public_talks.schedule.sheet_description') }}
                </SheetDescription>
            </SheetHeader>

            <div class="grid gap-6 px-4 pb-4">
                <div class="grid gap-2">
                    <Label>{{
                        t('app.public_talks.schedule.speaker_label')
                    }}</Label>
                    <Select v-model="speakerId">
                        <SelectTrigger
                            class="w-full"
                            data-test="speaker-select"
                        >
                            <SelectValue
                                :placeholder="
                                    t(
                                        'app.public_talks.schedule.speaker_placeholder',
                                    )
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="speaker in props.speakers"
                                :key="speaker.id"
                                :value="speaker.id"
                            >
                                <span class="flex items-center gap-2">
                                    {{ speaker.name }}
                                    <span
                                        v-if="!speaker.available"
                                        class="text-xs text-muted-foreground"
                                    >
                                        ({{
                                            t(
                                                'app.public_talks.schedule.unavailable',
                                            )
                                        }})
                                    </span>
                                </span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.speaker_id" />

                    <p
                        v-if="selectedSpeaker?.phone"
                        class="flex items-center gap-1 text-xs text-muted-foreground"
                    >
                        <Phone class="size-3" />
                        {{ selectedSpeaker.phone }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label>{{
                        t('app.public_talks.schedule.outline_label')
                    }}</Label>
                    <OutlinePicker
                        v-model="outlineId"
                        :outlines="sortedOutlines"
                        :prepared-ids="selectedSpeaker?.outline_ids ?? []"
                        :placeholder="
                            t('app.public_talks.schedule.outline_placeholder')
                        "
                        data-test="outline-select"
                    />
                    <InputError :message="errors.outline_id" />
                </div>
            </div>

            <SheetFooter class="flex-row flex-wrap justify-end gap-2 px-4 pb-6">
                <Button
                    v-if="canNotify"
                    variant="secondary"
                    :disabled="processing"
                    data-test="notify-speaker"
                    @click="notifySpeaker"
                >
                    {{ t('app.public_talks.schedule.notify') }}
                </Button>
                <Button
                    v-if="activeWeek?.speaker"
                    variant="outline"
                    :disabled="processing"
                    data-test="clear-slot"
                    @click="submit(true)"
                >
                    {{ t('app.public_talks.schedule.clear') }}
                </Button>
                <Button
                    :disabled="processing || !speakerId"
                    data-test="save-slot"
                    @click="submit(false)"
                >
                    {{ t('app.public_talks.schedule.save') }}
                </Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
