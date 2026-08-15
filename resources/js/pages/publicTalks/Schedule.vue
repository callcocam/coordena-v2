<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    ArrowDownLeft,
    ArrowUpRight,
    CalendarDays,
    MessageCircle,
    Phone,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import OutlinePicker from '@/components/OutlinePicker.vue';
import PageContainer from '@/components/PageContainer.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
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
    monthComplete: boolean;
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

/**
 * One card per calendar week: a `home` week stands alone and the two halves
 * of an exchange week (visitor coming in + our speaker going out) collapse
 * into a single "Semana de troca" card.
 */
type WeekGroup = {
    key: string;
    week_start: string;
    local: ScheduleWeek | null;
    incoming: ScheduleWeek | null;
    outgoing: ScheduleWeek | null;
};

const weekGroups = computed<WeekGroup[]>(() => {
    const groups = new Map<string, WeekGroup>();

    for (const week of props.weeks) {
        let group = groups.get(week.week_start);

        if (!group) {
            group = {
                key: week.week_start,
                week_start: week.week_start,
                local: null,
                incoming: null,
                outgoing: null,
            };
            groups.set(week.week_start, group);
        }

        if (week.type === 'home') {
            group.local = week;
        } else if (week.type === 'incoming') {
            group.incoming = week;
        } else {
            group.outgoing = week;
        }
    }

    return [...groups.values()];
});

const isExchange = (group: WeekGroup): boolean =>
    group.incoming !== null || group.outgoing !== null;

/**
 * Representative assignment of the exchange card header (the visitor's talk
 * happens at our hall, so it carries the meeting time).
 */
const exchangeHeaderWeek = (group: WeekGroup): ScheduleWeek | null =>
    group.incoming ?? group.outgoing;

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
const sheetMode = ref<'edit' | 'view'>('edit');
const activeWeek = ref<ScheduleWeek | null>(null);
const speakerId = ref<string | null>(null);
const outlineId = ref<string | null>(null);
const errors = ref<Record<string, string>>({});
const processing = ref(false);

/**
 * Whether the card opens something: editable weeks open the edit sheet and
 * outgoing weeks open a read-only detail sheet.
 */
const isOpenable = (week: ScheduleWeek): boolean =>
    week.editable || week.type === 'outgoing';

const openWeek = (week: ScheduleWeek) => {
    if (!isOpenable(week)) {
        return;
    }

    sheetMode.value = week.editable ? 'edit' : 'view';
    activeWeek.value = week;
    speakerId.value = week.speaker?.id ?? null;
    outlineId.value = week.outline?.id ?? null;
    errors.value = {};
    sheetOpen.value = true;
};

/**
 * Human label for the latest notification state of a week, if any.
 */
const notificationLabel = (week: ScheduleWeek): string | null => {
    if (!week.notification) {
        return null;
    }

    return t(
        `app.public_talks.schedule.notification.${week.notification.status}`,
        {
            date: week.notification.sent_at
                ? shortDateLabel(week.notification.sent_at)
                : '',
        },
    );
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
    () => activeWeek.value !== null && activeWeek.value.notifiable,
);

/* WhatsApp notify confirmation */
const confirmWeek = ref<ScheduleWeek | null>(null);

/**
 * First contact sends the assignment; any follow-up asks for confirmation.
 */
const notifyKind = (week: ScheduleWeek): 'assignment' | 'reminder' =>
    week.notification === null ? 'assignment' : 'reminder';

const requestNotify = (week: ScheduleWeek) => {
    confirmWeek.value = week;
};

const sendNotify = () => {
    const week = confirmWeek.value;

    if (!week) {
        return;
    }

    router.post(
        notifySlot([teamSlug.value, week.id]).url,
        { kind: notifyKind(week) },
        {
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
            onSuccess: () => {
                confirmWeek.value = null;
                sheetOpen.value = false;
            },
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
                v-if="props.canManage && !props.monthComplete"
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
                    t(
                        props.pendingCount === 1
                            ? 'app.public_talks.schedule.pending_badge_one'
                            : 'app.public_talks.schedule.pending_badge_other',
                        { count: props.pendingCount },
                    )
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
            <template v-for="group in weekGroups" :key="group.key">
                <!-- Local week: one assignment, whole card opens the sheet. -->
                <div
                    v-for="week in group.local ? [group.local] : []"
                    :key="week.id"
                    :role="isOpenable(week) ? 'button' : undefined"
                    :tabindex="isOpenable(week) ? 0 : undefined"
                    data-test="week-row"
                    class="w-full rounded-lg border p-4 text-left transition-colors"
                    :class="
                        isOpenable(week)
                            ? 'cursor-pointer hover:bg-accent'
                            : 'cursor-default'
                    "
                    @click="openWeek(week)"
                    @keydown.enter="openWeek(week)"
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
                                            date: shortDateLabel(
                                                week.week_start,
                                            ),
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
                                <template v-if="week.speaker">
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
                                    {{
                                        t(
                                            'app.public_talks.schedule.no_speaker',
                                        )
                                    }}
                                </span>
                            </div>

                            <div
                                v-if="notificationLabel(week)"
                                class="mt-1 text-xs text-muted-foreground"
                                data-test="notification-state"
                            >
                                {{ notificationLabel(week) }}
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
                                        ? t(
                                              'app.public_talks.schedule.edit_week',
                                          )
                                        : t(
                                              'app.public_talks.schedule.edit_slot',
                                          )
                                }}
                            </span>

                            <Button
                                v-if="week.notifiable"
                                variant="secondary"
                                size="sm"
                                data-test="card-notify"
                                @click.stop="requestNotify(week)"
                            >
                                <MessageCircle class="size-4" />
                                {{
                                    t(
                                        `app.public_talks.schedule.notify_${notifyKind(week)}`,
                                    )
                                }}
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Exchange week: visitor coming in + our speaker going out, one card. -->
                <div
                    v-if="isExchange(group)"
                    data-test="exchange-week-row"
                    class="w-full rounded-lg border p-4"
                >
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-2">
                            <CalendarDays
                                class="size-4 shrink-0 text-muted-foreground"
                            />
                            <span class="font-medium">
                                {{
                                    t('app.public_talks.schedule.week_of', {
                                        date: shortDateLabel(group.week_start),
                                    })
                                }}
                            </span>
                            <Badge variant="outline">
                                {{
                                    t('app.public_talks.schedule.exchange_week')
                                }}
                            </Badge>
                        </div>
                        <span
                            v-if="exchangeHeaderWeek(group)"
                            class="text-xs text-muted-foreground capitalize"
                        >
                            {{ meetingLabel(exchangeHeaderWeek(group)!) }}
                        </span>
                    </div>

                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div
                            v-for="direction in (
                                [
                                    ['incoming', group.incoming],
                                    ['outgoing', group.outgoing],
                                ] as const
                            ).filter(([, week]) => week !== null)"
                            :key="direction[0]"
                            class="rounded-md border p-3"
                            :class="
                                direction[0] === 'incoming'
                                    ? 'border-emerald-500/40 bg-emerald-500/5'
                                    : 'border-sky-500/40 bg-sky-500/5'
                            "
                            :data-test="`exchange-${direction[0]}`"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <ArrowDownLeft
                                            v-if="direction[0] === 'incoming'"
                                            class="size-4 shrink-0 text-emerald-600"
                                        />
                                        <ArrowUpRight
                                            v-else
                                            class="size-4 shrink-0 text-sky-600"
                                        />
                                        <span class="text-sm font-semibold">
                                            {{
                                                t(
                                                    `app.public_talks.schedule.sections.${direction[0]}`,
                                                )
                                            }}
                                        </span>
                                        <Badge
                                            :variant="
                                                statusVariant(
                                                    direction[1]!.status,
                                                )
                                            "
                                        >
                                            {{
                                                t(
                                                    `app.public_talks.schedule.statuses.${direction[1]!.status}`,
                                                )
                                            }}
                                        </Badge>
                                    </div>

                                    <div class="mt-2 text-sm">
                                        <template v-if="direction[1]!.speaker">
                                            <span class="font-medium">
                                                {{ direction[1]!.speaker.name }}
                                            </span>
                                            <span
                                                v-if="direction[1]!.counterpart"
                                                class="text-muted-foreground"
                                            >
                                                {{
                                                    t(
                                                        direction[0] ===
                                                            'incoming'
                                                            ? 'app.public_talks.schedule.incoming_from'
                                                            : 'app.public_talks.schedule.outgoing_to',
                                                        {
                                                            name: direction[1]!
                                                                .counterpart,
                                                        },
                                                    )
                                                }}
                                            </span>
                                        </template>
                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                        >
                                            {{
                                                direction[0] === 'outgoing'
                                                    ? (direction[1]!
                                                          .counterpart ?? '—')
                                                    : t(
                                                          'app.public_talks.schedule.no_speaker',
                                                      )
                                            }}
                                        </span>
                                    </div>

                                    <div
                                        v-if="direction[1]!.outline"
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        nº {{ direction[1]!.outline.number }} ·
                                        {{ direction[1]!.outline.title }}
                                    </div>

                                    <div
                                        v-if="notificationLabel(direction[1]!)"
                                        class="mt-1 text-xs text-muted-foreground"
                                        data-test="notification-state"
                                    >
                                        {{ notificationLabel(direction[1]!) }}
                                    </div>

                                    <div
                                        v-if="
                                            direction[1]!.speaker &&
                                            !direction[1]!.speaker.phone
                                        "
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{
                                            t(
                                                'app.public_talks.schedule.no_phone',
                                            )
                                        }}
                                    </div>
                                </div>

                                <div
                                    class="flex shrink-0 flex-col items-end gap-2"
                                >
                                    <Button
                                        v-if="direction[1]!.notifiable"
                                        variant="secondary"
                                        size="sm"
                                        data-test="card-notify"
                                        @click="requestNotify(direction[1]!)"
                                    >
                                        <MessageCircle class="size-4" />
                                        {{
                                            t(
                                                `app.public_talks.schedule.notify_${notifyKind(direction[1]!)}`,
                                            )
                                        }}
                                    </Button>
                                    <Button
                                        v-if="direction[0] === 'outgoing'"
                                        variant="outline"
                                        size="sm"
                                        data-test="week-action"
                                        @click="openWeek(direction[1]!)"
                                    >
                                        {{
                                            t(
                                                'app.public_talks.schedule.view_week',
                                            )
                                        }}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
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
                    {{
                        sheetMode === 'view'
                            ? t(
                                  'app.public_talks.schedule.outgoing_sheet_description',
                              )
                            : t('app.public_talks.schedule.sheet_description')
                    }}
                </SheetDescription>
            </SheetHeader>

            <div
                v-if="sheetMode === 'view' && activeWeek"
                class="grid gap-4 px-4 pb-4 text-sm"
                data-test="view-week-details"
            >
                <div class="grid gap-1">
                    <span class="text-xs text-muted-foreground">
                        {{ t('app.public_talks.schedule.speaker_label') }}
                    </span>
                    <span>
                        {{
                            activeWeek.speaker?.name ??
                            t('app.public_talks.schedule.no_speaker')
                        }}
                    </span>
                    <span
                        v-if="activeWeek.speaker?.phone"
                        class="flex items-center gap-1 text-xs text-muted-foreground"
                    >
                        <Phone class="size-3" />
                        {{ activeWeek.speaker.phone }}
                    </span>
                </div>

                <div class="grid gap-1">
                    <span class="text-xs text-muted-foreground">
                        {{ t('app.public_talks.schedule.counterpart_label') }}
                    </span>
                    <span>
                        {{
                            activeWeek.counterpart ??
                            t('app.public_talks.schedule.no_counterpart')
                        }}
                    </span>
                </div>

                <div v-if="activeWeek.outline" class="grid gap-1">
                    <span class="text-xs text-muted-foreground">
                        {{ t('app.public_talks.schedule.outline_label') }}
                    </span>
                    <span>
                        nº {{ activeWeek.outline.number }} ·
                        {{ activeWeek.outline.title }}
                    </span>
                </div>

                <div v-if="notificationLabel(activeWeek)" class="grid gap-1">
                    <span class="text-xs text-muted-foreground">
                        {{ notificationLabel(activeWeek) }}
                    </span>
                </div>
            </div>

            <div v-else class="grid gap-6 px-4 pb-4">
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
                    v-if="canNotify && activeWeek"
                    variant="secondary"
                    :disabled="processing"
                    data-test="notify-speaker"
                    @click="requestNotify(activeWeek)"
                >
                    <MessageCircle class="size-4" />
                    {{
                        t(
                            `app.public_talks.schedule.notify_${notifyKind(activeWeek)}`,
                        )
                    }}
                </Button>
                <template v-if="sheetMode === 'edit'">
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
                </template>
            </SheetFooter>
        </SheetContent>
    </Sheet>

    <AlertDialog
        :open="confirmWeek !== null"
        @update:open="(open) => !open && (confirmWeek = null)"
    >
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>
                    {{ t('app.public_talks.schedule.notify_confirm_title') }}
                </AlertDialogTitle>
                <AlertDialogDescription v-if="confirmWeek">
                    {{
                        t(
                            `app.public_talks.schedule.notify_confirm_${notifyKind(confirmWeek)}`,
                            {
                                speaker: confirmWeek.speaker?.name ?? '',
                                date: shortDateLabel(confirmWeek.date),
                            },
                        )
                    }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel :disabled="processing">
                    {{ t('app.public_talks.schedule.notify_confirm_cancel') }}
                </AlertDialogCancel>
                <AlertDialogAction
                    :disabled="processing"
                    data-test="confirm-notify"
                    @click="sendNotify"
                >
                    {{ t('app.public_talks.schedule.notify_confirm_send') }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
