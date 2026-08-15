<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ChevronsUpDown, Plus, Search, Send, Trash2, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import OutlinePicker from '@/components/OutlinePicker.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useT } from '@/composables/useT';
import { submit as submitPortal } from '@/routes/exchange/portal';
import PhoneInput from '@whatsapp-cloud/components/PhoneInput/PhoneInput.vue';

type Outline = { id: string; number: number; title: string };

type IncomingRow = {
    week: string;
    speaker_id: string;
    speaker_name: string;
    phone: string;
    outline_ids: string[];
};

type PartnerSpeaker = {
    id: string;
    name: string;
    phone: string | null;
    outline_ids: string[];
};

type OutgoingRow = {
    week: string;
    speaker_id: string;
    outline_id: string;
};

type OutlineRef = { number: number; title: string };

type ArrangementItem = {
    week: string | null;
    direction: 'incoming' | 'outgoing';
    speaker_name: string;
    outline: OutlineRef | null;
    status: string;
};

type RecentOutline = {
    date: string;
    outline: OutlineRef;
    speaker_name: string | null;
};

type Props = {
    token: string;
    month: string;
    homeCongregation: string | null;
    meetingTime: string | null;
    invitedCongregation: string;
    closed: boolean;
    openWeeks: { date: string }[];
    monthWeeks: { week: string }[];
    homeSpeakers: { id: string; name: string; outlines: Outline[] }[];
    partnerSpeakers: PartnerSpeaker[];
    outlineCatalog: Outline[];
    arrangement: ArrangementItem[];
    recentOutlines: RecentOutline[];
    helpUrl: string;
    expiresAt: string | null;
};

const props = defineProps<Props>();

const { t } = useT();

const submitted = ref(false);

const form = useForm({
    incoming: props.openWeeks.map((week): IncomingRow => ({
        week: week.date,
        speaker_id: '',
        speaker_name: '',
        phone: '',
        outline_ids: [],
    })),
    outgoing: [] as OutgoingRow[],
});

const isRowFilled = (row: IncomingRow): boolean =>
    row.speaker_id !== '' || row.speaker_name.trim() !== '';

const filledIncoming = computed(() => form.incoming.filter(isRowFilled));

const canSubmit = computed(
    () => filledIncoming.value.length > 0 || form.outgoing.length > 0,
);

const monthLabel = (): string => {
    const [year, monthNumber] = props.month.split('-');

    return new Date(
        Number(year),
        Number(monthNumber) - 1,
        1,
    ).toLocaleDateString('pt-BR', {
        month: 'long',
        year: 'numeric',
    });
};

const dateLabel = (date: string): string =>
    new Date(`${date}T12:00:00`).toLocaleDateString('pt-BR', {
        weekday: 'short',
        day: '2-digit',
        month: '2-digit',
    });

const openWeekLabel = (date: string): string => {
    const label = dateLabel(date);

    return props.meetingTime !== null
        ? `${label} · ${props.meetingTime}`
        : label;
};

const weekOfLabel = (week: string): string =>
    t('app.public_talks.exchange.portal.week_of', {
        date: new Date(`${week}T12:00:00`).toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
        }),
    });

const speakerById = (id: string) =>
    props.homeSpeakers.find((speaker) => speaker.id === id);

const partnerSpeakerById = (id: string) =>
    props.partnerSpeakers.find((speaker) => speaker.id === id);

const outlineById = (id: string) =>
    props.outlineCatalog.find((outline) => outline.id === id);

const normalize = (value: string): string =>
    value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

const speakerSearch = ref('');
const openSpeakerPopover = ref<number | null>(null);

const usedIncomingSpeakerIds = computed<Set<string>>(
    () =>
        new Set(
            form.incoming
                .filter((_, index) => index !== openSpeakerPopover.value)
                .map((row) => row.speaker_id)
                .filter((id) => id !== ''),
        ),
);

const filteredPartnerSpeakers = computed(() => {
    const term = normalize(speakerSearch.value.trim());
    const available = props.partnerSpeakers.filter(
        (speaker) => !usedIncomingSpeakerIds.value.has(speaker.id),
    );

    if (term === '') {
        return available;
    }

    return available.filter((speaker) =>
        normalize(speaker.name).includes(term),
    );
});

const incomingSpeakerLabel = (row: IncomingRow): string | null =>
    row.speaker_id !== ''
        ? (partnerSpeakerById(row.speaker_id)?.name ?? null)
        : row.speaker_name.trim() || null;

const toggleSpeakerPopover = (index: number, open: boolean) => {
    openSpeakerPopover.value = open ? index : null;
    speakerSearch.value = '';
};

const pickPartnerSpeaker = (row: IncomingRow, speaker: PartnerSpeaker) => {
    row.speaker_id = speaker.id;
    row.speaker_name = '';
    row.outline_ids = [...speaker.outline_ids];

    if (speaker.phone && row.phone.trim() === '') {
        row.phone = speaker.phone;
    }

    openSpeakerPopover.value = null;
};

const useTypedSpeakerName = (row: IncomingRow) => {
    const name = speakerSearch.value.trim();

    if (name === '') {
        return;
    }

    row.speaker_id = '';
    row.speaker_name = name;
    row.outline_ids = [];
    openSpeakerPopover.value = null;
};

const isHomeSpeakerTaken = (index: number, speakerId: string): boolean =>
    form.outgoing.some(
        (row, rowIndex) => rowIndex !== index && row.speaker_id === speakerId,
    );

const isOutgoingWeekTaken = (index: number, week: string): boolean =>
    form.outgoing.some(
        (row, rowIndex) => rowIndex !== index && row.week === week,
    );

const clearIncomingSpeaker = (row: IncomingRow) => {
    row.speaker_id = '';
    row.speaker_name = '';
    row.outline_ids = [];
};

const toggleIncomingOutline = (row: IncomingRow, outlineId: string) => {
    row.outline_ids = row.outline_ids.includes(outlineId)
        ? row.outline_ids.filter((id) => id !== outlineId)
        : [...row.outline_ids, outlineId];
};

const addOutgoingRow = () => {
    const usedWeeks = new Set(form.outgoing.map((row) => row.week));
    const nextWeek =
        props.monthWeeks.find((week) => !usedWeeks.has(week.week)) ??
        props.monthWeeks[0];

    form.outgoing.push({
        week: nextWeek?.week ?? '',
        speaker_id: '',
        outline_id: '',
    });
};

const removeOutgoingRow = (index: number) => {
    form.outgoing.splice(index, 1);
};

const onSpeakerChange = (row: OutgoingRow) => {
    row.outline_id = '';
};

const arrangementStatusVariant = (
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (status === 'confirmed' || status === 'accepted') {
        return 'default';
    }

    if (status === 'declined' || status === 'discarded') {
        return 'destructive';
    }

    return 'secondary';
};

const fullDateLabel = (date: string): string =>
    new Date(`${date}T12:00:00`).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });

/**
 * Server errors are keyed by the index inside the SUBMITTED (filtered)
 * incoming array; map a UI row back to that index.
 */
const incomingError = (index: number, field: string): string | undefined => {
    const submittedIndex =
        form.incoming.slice(0, index + 1).filter(isRowFilled).length - 1;

    if (!isRowFilled(form.incoming[index]) || submittedIndex < 0) {
        return undefined;
    }

    return (form.errors as Record<string, string>)[
        `incoming.${submittedIndex}.${field}`
    ];
};

const outgoingError = (index: number, field: string): string | undefined =>
    (form.errors as Record<string, string>)[`outgoing.${index}.${field}`];

const submit = () => {
    if (!canSubmit.value) {
        return;
    }

    form.transform((data) => ({
        incoming: data.incoming.filter(isRowFilled).map((row) => ({
            week: row.week,
            speaker_id: row.speaker_id || null,
            speaker_name: row.speaker_id ? null : row.speaker_name.trim(),
            phone: row.phone || null,
            outline_ids: row.outline_ids,
        })),
        outgoing: data.outgoing,
    })).post(submitPortal(props.token).url, {
        preserveScroll: true,
        onSuccess: () => (submitted.value = true),
    });
};
</script>

<template>
    <Head
        :title="
            t('app.public_talks.exchange.portal.title', {
                home: props.homeCongregation ?? '',
            })
        "
    />

    <div
        class="mx-auto flex min-h-svh w-full max-w-3xl flex-col gap-6 p-4 py-10"
    >
        <div class="flex flex-col gap-2 text-center">
            <h1 class="text-xl font-semibold">
                {{
                    t('app.public_talks.exchange.portal.title', {
                        home: props.homeCongregation ?? '',
                    })
                }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{
                    t('app.public_talks.exchange.portal.description', {
                        month: monthLabel(),
                    })
                }}
            </p>
            <p
                v-if="!props.closed && props.expiresAt"
                class="text-xs text-muted-foreground"
                data-test="portal-expires"
            >
                {{
                    t('app.public_talks.exchange.portal.expires_at', {
                        date: fullDateLabel(props.expiresAt),
                    })
                }}
            </p>
        </div>

        <section
            v-if="props.arrangement.length > 0"
            class="flex flex-col gap-3"
            data-test="portal-arrangement"
        >
            <div>
                <h2 class="text-sm font-semibold">
                    {{
                        t('app.public_talks.exchange.portal.arrangement_title')
                    }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ t('app.public_talks.exchange.portal.arrangement_help') }}
                </p>
            </div>
            <div class="flex flex-col gap-2">
                <div
                    v-for="(item, index) in props.arrangement"
                    :key="index"
                    class="flex flex-wrap items-center gap-2 rounded-lg border p-3 text-sm"
                    data-test="arrangement-item"
                >
                    <Badge variant="outline">
                        {{
                            t(
                                item.direction === 'incoming'
                                    ? 'app.public_talks.exchange.portal.arrangement_incoming'
                                    : 'app.public_talks.exchange.portal.arrangement_outgoing',
                            )
                        }}
                    </Badge>
                    <span v-if="item.week" class="text-muted-foreground">
                        {{ dateLabel(item.week) }}
                    </span>
                    <span class="font-medium">{{ item.speaker_name }}</span>
                    <span
                        v-if="item.outline"
                        class="truncate text-muted-foreground"
                    >
                        nº {{ item.outline.number }} —
                        {{ item.outline.title }}
                    </span>
                    <Badge
                        :variant="arrangementStatusVariant(item.status)"
                        class="ml-auto"
                    >
                        {{
                            t(
                                `app.public_talks.exchange.portal.arrangement_statuses.${item.status}`,
                            )
                        }}
                    </Badge>
                </div>
            </div>
        </section>

        <section
            v-if="props.recentOutlines.length > 0"
            class="flex flex-col gap-3"
            data-test="portal-recent-outlines"
        >
            <div>
                <h2 class="text-sm font-semibold">
                    {{ t('app.public_talks.exchange.portal.recent_title') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ t('app.public_talks.exchange.portal.recent_help') }}
                </p>
            </div>
            <ul class="flex flex-col gap-1 rounded-lg border p-3 text-sm">
                <li
                    v-for="(item, index) in props.recentOutlines"
                    :key="index"
                    class="flex flex-wrap items-center gap-2"
                    data-test="recent-outline-item"
                >
                    <span class="text-muted-foreground">
                        {{ dateLabel(item.date) }}
                    </span>
                    <span>
                        nº {{ item.outline.number }} —
                        {{ item.outline.title }}
                    </span>
                    <span
                        v-if="item.speaker_name"
                        class="text-muted-foreground"
                    >
                        {{
                            t('app.public_talks.exchange.portal.recent_by', {
                                speaker: item.speaker_name,
                            })
                        }}
                    </span>
                </li>
            </ul>
        </section>

        <div
            v-if="props.closed"
            class="rounded-lg border p-6 text-center text-sm text-muted-foreground"
            data-test="portal-closed"
        >
            {{ t('app.public_talks.exchange.portal.expired') }}
        </div>

        <div
            v-else-if="submitted"
            class="rounded-lg border p-6 text-center"
            data-test="portal-thanks"
        >
            <p class="text-sm font-medium">
                {{ t('app.public_talks.exchange.portal.thanks_title') }}
            </p>
            <p class="mt-1 text-sm text-muted-foreground">
                {{
                    t('app.public_talks.exchange.portal.thanks_description', {
                        home: props.homeCongregation ?? '',
                    })
                }}
            </p>
        </div>

        <form v-else class="flex flex-col gap-6" @submit.prevent="submit">
            <section class="flex flex-col gap-3">
                <div>
                    <h2 class="text-sm font-semibold">
                        {{
                            t('app.public_talks.exchange.portal.incoming_title')
                        }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{
                            t('app.public_talks.exchange.portal.incoming_help')
                        }}
                    </p>
                </div>

                <p
                    v-if="form.incoming.length === 0"
                    class="rounded-lg border p-4 text-sm text-muted-foreground"
                >
                    {{ t('app.public_talks.exchange.portal.incoming_empty') }}
                </p>

                <div
                    v-for="(row, index) in form.incoming"
                    :key="row.week"
                    class="flex flex-col gap-3 rounded-lg border p-4"
                    data-test="incoming-week"
                >
                    <span class="text-sm font-medium">{{
                        openWeekLabel(row.week)
                    }}</span>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label :for="`incoming-name-${index}`">
                                {{
                                    t(
                                        'app.public_talks.exchange.portal.speaker_name_label',
                                    )
                                }}
                            </Label>
                            <div class="flex items-center gap-1">
                                <Popover
                                    :open="openSpeakerPopover === index"
                                    @update:open="
                                        (open) =>
                                            toggleSpeakerPopover(index, open)
                                    "
                                >
                                    <PopoverTrigger as-child>
                                        <Button
                                            :id="`incoming-name-${index}`"
                                            variant="outline"
                                            role="combobox"
                                            class="w-full justify-between font-normal"
                                            data-test="incoming-speaker"
                                        >
                                            <span
                                                class="truncate"
                                                :class="{
                                                    'text-muted-foreground':
                                                        !incomingSpeakerLabel(
                                                            row,
                                                        ),
                                                }"
                                            >
                                                {{
                                                    incomingSpeakerLabel(row) ??
                                                    t(
                                                        'app.public_talks.exchange.portal.speaker_placeholder',
                                                    )
                                                }}
                                            </span>
                                            <ChevronsUpDown
                                                class="size-4 shrink-0 opacity-50"
                                            />
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent
                                        class="w-(--reka-popover-trigger-width) p-2"
                                        align="start"
                                    >
                                        <div class="relative mb-2">
                                            <Search
                                                class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                                            />
                                            <Input
                                                v-model="speakerSearch"
                                                class="h-8 pl-8"
                                                :placeholder="
                                                    t(
                                                        'app.public_talks.exchange.portal.speaker_search_placeholder',
                                                    )
                                                "
                                                data-test="incoming-speaker-search"
                                            />
                                        </div>
                                        <div
                                            class="max-h-56 space-y-0.5 overflow-y-auto"
                                        >
                                            <div
                                                v-for="speaker in filteredPartnerSpeakers"
                                                :key="speaker.id"
                                                class="flex cursor-pointer items-center gap-2 rounded p-1.5 text-sm hover:bg-accent"
                                                :class="{
                                                    'bg-accent':
                                                        row.speaker_id ===
                                                        speaker.id,
                                                }"
                                                data-test="incoming-speaker-item"
                                                @click="
                                                    pickPartnerSpeaker(
                                                        row,
                                                        speaker,
                                                    )
                                                "
                                            >
                                                <span
                                                    class="min-w-0 flex-1 truncate"
                                                    >{{ speaker.name }}</span
                                                >
                                            </div>
                                        </div>
                                        <button
                                            v-if="speakerSearch.trim() !== ''"
                                            type="button"
                                            class="mt-1 w-full rounded p-1.5 text-left text-sm hover:bg-accent"
                                            data-test="incoming-speaker-create"
                                            @click="useTypedSpeakerName(row)"
                                        >
                                            {{
                                                t(
                                                    'app.public_talks.exchange.portal.speaker_use_new',
                                                    {
                                                        name: speakerSearch.trim(),
                                                    },
                                                )
                                            }}
                                        </button>
                                    </PopoverContent>
                                </Popover>
                                <Button
                                    v-if="incomingSpeakerLabel(row)"
                                    variant="ghost"
                                    size="icon"
                                    type="button"
                                    data-test="incoming-speaker-clear"
                                    @click="clearIncomingSpeaker(row)"
                                >
                                    <X class="size-4" />
                                </Button>
                            </div>
                            <InputError
                                :message="incomingError(index, 'speaker_name')"
                            />
                            <InputError
                                :message="incomingError(index, 'speaker_id')"
                            />
                            <InputError
                                :message="incomingError(index, 'week')"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`incoming-phone-${index}`">
                                {{
                                    t(
                                        'app.public_talks.exchange.portal.phone_label',
                                    )
                                }}
                            </Label>
                            <PhoneInput
                                :id="`incoming-phone-${index}`"
                                v-model="row.phone"
                            />
                            <InputError
                                :message="incomingError(index, 'phone')"
                            />
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <Label>
                                {{
                                    t(
                                        'app.public_talks.exchange.portal.outline_ids_label',
                                    )
                                }}
                            </Label>
                            <div
                                v-if="row.outline_ids.length > 0"
                                class="flex flex-wrap gap-1.5"
                            >
                                <Badge
                                    v-for="outlineId in row.outline_ids"
                                    :key="outlineId"
                                    variant="secondary"
                                    class="gap-1"
                                    data-test="incoming-outline-tag"
                                >
                                    {{ outlineById(outlineId)?.number }}.
                                    {{ outlineById(outlineId)?.title }}
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-foreground"
                                        :aria-label="
                                            t(
                                                'app.public_talks.exchange.portal.outline_remove',
                                            )
                                        "
                                        data-test="incoming-outline-remove"
                                        @click="
                                            toggleIncomingOutline(
                                                row,
                                                outlineId,
                                            )
                                        "
                                    >
                                        <X class="size-3" />
                                    </button>
                                </Badge>
                            </div>
                            <OutlinePicker
                                multiple
                                popover
                                :outlines="props.outlineCatalog"
                                :selected-ids="row.outline_ids"
                                :placeholder="
                                    t(
                                        'app.public_talks.exchange.portal.outline_add',
                                    )
                                "
                                data-test="incoming-outlines"
                                @toggle="
                                    (id: string) =>
                                        toggleIncomingOutline(row, id)
                                "
                            />
                            <InputError
                                :message="incomingError(index, 'outline_ids')"
                            />
                        </div>
                    </div>
                </div>
            </section>

            <section class="flex flex-col gap-3">
                <div>
                    <h2 class="text-sm font-semibold">
                        {{
                            t('app.public_talks.exchange.portal.outgoing_title')
                        }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{
                            t('app.public_talks.exchange.portal.outgoing_help')
                        }}
                    </p>
                </div>

                <div
                    v-for="(row, index) in form.outgoing"
                    :key="index"
                    class="flex flex-col gap-3 rounded-lg border p-4"
                    data-test="outgoing-week"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium">{{
                            weekOfLabel(row.week)
                        }}</span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            data-test="remove-outgoing"
                            @click="removeOutgoingRow(index)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label>{{
                                t('app.public_talks.exchange.portal.week_label')
                            }}</Label>
                            <Select v-model="row.week">
                                <SelectTrigger
                                    class="w-full"
                                    :data-test="`outgoing-week-${index}`"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="week in props.monthWeeks"
                                        :key="week.week"
                                        :value="week.week"
                                        :disabled="
                                            isOutgoingWeekTaken(
                                                index,
                                                week.week,
                                            )
                                        "
                                    >
                                        {{ weekOfLabel(week.week) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="outgoingError(index, 'week')"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label>{{
                                t(
                                    'app.public_talks.exchange.portal.our_speaker_label',
                                )
                            }}</Label>
                            <Select
                                v-model="row.speaker_id"
                                @update:model-value="onSpeakerChange(row)"
                            >
                                <SelectTrigger
                                    class="w-full"
                                    :data-test="`outgoing-speaker-${index}`"
                                >
                                    <SelectValue
                                        :placeholder="
                                            t(
                                                'app.public_talks.exchange.portal.our_speaker_placeholder',
                                            )
                                        "
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="speaker in props.homeSpeakers"
                                        :key="speaker.id"
                                        :value="speaker.id"
                                        :disabled="
                                            isHomeSpeakerTaken(
                                                index,
                                                speaker.id,
                                            )
                                        "
                                    >
                                        {{ speaker.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="outgoingError(index, 'speaker_id')"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label>{{
                                t(
                                    'app.public_talks.exchange.portal.outline_choice_label',
                                )
                            }}</Label>
                            <Select
                                v-model="row.outline_id"
                                :disabled="row.speaker_id === ''"
                            >
                                <SelectTrigger
                                    class="w-full"
                                    :data-test="`outgoing-outline-${index}`"
                                >
                                    <SelectValue
                                        :placeholder="
                                            t(
                                                'app.public_talks.exchange.portal.outline_choice_placeholder',
                                            )
                                        "
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="outline in speakerById(
                                            row.speaker_id,
                                        )?.outlines ?? []"
                                        :key="outline.id"
                                        :value="outline.id"
                                    >
                                        {{ outline.number }} —
                                        {{ outline.title }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="outgoingError(index, 'outline_id')"
                            />
                        </div>
                    </div>
                </div>

                <div>
                    <Button
                        type="button"
                        variant="outline"
                        data-test="add-outgoing"
                        :disabled="
                            props.homeSpeakers.length === 0 ||
                            props.monthWeeks.length === 0
                        "
                        @click="addOutgoingRow"
                    >
                        <Plus class="size-4" />
                        {{ t('app.public_talks.exchange.portal.add_week') }}
                    </Button>
                </div>
            </section>

            <InputError :message="form.errors.incoming" />
            <InputError :message="form.errors.outgoing" />

            <div class="flex items-center justify-end gap-3">
                <p v-if="!canSubmit" class="text-sm text-muted-foreground">
                    {{
                        t('app.public_talks.exchange.portal.nothing_to_submit')
                    }}
                </p>
                <Button
                    type="submit"
                    :disabled="form.processing || !canSubmit"
                    data-test="portal-submit"
                >
                    <Send class="size-4" />
                    {{ t('app.public_talks.exchange.portal.submit') }}
                </Button>
            </div>
        </form>

        <section
            v-if="!props.closed && !submitted"
            class="flex flex-col gap-2 rounded-lg border border-dashed p-4"
            data-test="portal-tips"
        >
            <h2 class="text-sm font-semibold">
                {{ t('app.public_talks.exchange.portal.tips_title') }}
            </h2>
            <ul class="list-disc space-y-1 pl-4 text-sm text-muted-foreground">
                <li>
                    {{
                        t('app.public_talks.exchange.portal.tips.fill_partial')
                    }}
                </li>
                <li>
                    {{
                        t(
                            'app.public_talks.exchange.portal.tips.phone_optional',
                        )
                    }}
                </li>
                <li>
                    {{
                        t('app.public_talks.exchange.portal.tips.review_later')
                    }}
                </li>
            </ul>
        </section>

        <p class="text-center text-sm">
            <a
                :href="props.helpUrl"
                class="text-muted-foreground underline underline-offset-4 hover:text-foreground"
                data-test="portal-help-link"
            >
                {{ t('app.public_talks.exchange.portal.help_link') }}
            </a>
        </p>
    </div>
</template>
