<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Check, Copy, MessageSquarePlus, Plus, Trash2, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PageContainer from '@/components/PageContainer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as exchangeIndex } from '@/routes/public-talks/exchange';
import {
    accept as acceptOffer,
    decline as declineOffer,
    destroy as destroyOffer,
    outline as chooseOutline,
    store as storeOffer,
} from '@/routes/public-talks/exchange/offers';
import {
    confirm as confirmSend,
    decline as declineSend,
} from '@/routes/public-talks/exchange/sends';
import { store as storeReply } from '@/routes/public-talks/exchange/sends/replies';

type OutlineItem = { id: string; number: number; title: string };

type OfferItem = {
    id: string;
    direction: 'incoming' | 'outgoing';
    status: string;
    target_date: string | null;
    chosen_outline_id: string | null;
    source_message_id: string | null;
    speaker: { id: string; name: string; phone: string | null };
    outlines: OutlineItem[];
};

type SpeakerItem = {
    id: string;
    name: string;
    outline_ids: string[];
};

type MessageItem = {
    id: string;
    direction: string;
    body: string;
    created_at: string | null;
};

const props = defineProps<{
    month: string;
    send: {
        id: string;
        status: string;
        sent_at: string | null;
        answered_at: string | null;
        portal_url: string;
        congregation: {
            id: string;
            name: string;
            meeting_weekday: number | null;
            meeting_time: string | null;
        };
    };
    offers: OfferItem[];
    messages: MessageItem[];
    openWeeks: { id: string; date: string }[];
    counterpartSpeakers: SpeakerItem[];
    homeSpeakers: SpeakerItem[];
    outlines: OutlineItem[];
    canManage: boolean;
}>();

defineOptions({ layout: AppLayout });

const { t } = useT();
const page = usePage();

const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const processing = ref(false);
const copied = ref(false);
const replyBody = ref('');
const showOfferForm = ref(false);

const offerForm = ref<{
    direction: 'incoming' | 'outgoing';
    speaker_id: string;
    target_date: string;
    outline_ids: string[];
    source_message_id: string | null;
}>({
    direction: 'incoming',
    speaker_id: '',
    target_date: '',
    outline_ids: [],
    source_message_id: null,
});

const errors = computed(() => page.props.errors as Record<string, string>);

const outlinesById = computed(() => {
    const map = new Map<string, OutlineItem>();
    props.outlines.forEach((outline) => map.set(outline.id, outline));
    return map;
});

const activeOffers = computed(() =>
    props.offers.filter((offer) => !['discarded', 'declined'].includes(offer.status)),
);

const incomingCount = computed(
    () => activeOffers.value.filter((offer) => offer.direction === 'incoming').length,
);

const outgoingCount = computed(
    () => activeOffers.value.filter((offer) => offer.direction === 'outgoing').length,
);

const acceptedOffers = computed(() =>
    props.offers.filter((offer) => offer.status === 'accepted'),
);

const canConfirm = computed(
    () =>
        acceptedOffers.value.length > 0 &&
        acceptedOffers.value.every(
            (offer) => offer.direction === 'outgoing' || offer.chosen_outline_id !== null,
        ),
);

/** Offers grouped by week (null weeks last), each group split by direction. */
const weekGroups = computed(() => {
    const groups = new Map<string, { incoming: OfferItem[]; outgoing: OfferItem[] }>();

    props.offers.forEach((offer) => {
        const key = offer.target_date ?? '';

        if (!groups.has(key)) {
            groups.set(key, { incoming: [], outgoing: [] });
        }

        groups.get(key)![offer.direction].push(offer);
    });

    return [...groups.entries()]
        .sort(([a], [b]) => {
            if (a === '') return 1;
            if (b === '') return -1;
            return a.localeCompare(b);
        })
        .map(([date, sides]) => ({ date: date === '' ? null : date, ...sides }));
});

function weekLabel(date: string | null): string {
    if (date === null) {
        return t('app.public_talks.exchange.workbench.week_placeholder');
    }

    return new Date(`${date}T12:00:00`).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
    });
}

const meetingInfo = computed(() => {
    const { meeting_weekday, meeting_time } = props.send.congregation;

    if (meeting_weekday === null) {
        return null;
    }

    return t('app.public_talks.exchange.workbench.meeting_info', {
        weekday: t(`app.public_talks.weekdays.${meeting_weekday}`),
        time: meeting_time?.slice(0, 5) ?? '—',
    });
});

function outlineLabel(outline: OutlineItem): string {
    return `${outline.number} — ${outline.title}`;
}

function chosenOutlineLabel(offer: OfferItem): string {
    const id = offer.chosen_outline_id ?? offer.outlines[0]?.id ?? null;
    const outline = id !== null ? (outlinesById.value.get(id) ?? offer.outlines.find((item) => item.id === id)) : null;

    return outline ? outlineLabel(outline) : '—';
}

function statusBadge(offer: OfferItem): { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' } | null {
    switch (offer.status) {
        case 'accepted':
            return { label: t('app.public_talks.exchange.workbench.accepted_badge'), variant: 'default' };
        case 'declined':
            return { label: t('app.public_talks.exchange.workbench.declined_badge'), variant: 'destructive' };
        case 'confirmed':
            return { label: t('app.public_talks.exchange.workbench.confirmed_badge'), variant: 'default' };
        case 'discarded':
            return { label: t('app.public_talks.exchange.workbench.discarded_badge'), variant: 'outline' };
        default:
            return null;
    }
}

const offerLocked = (offer: OfferItem): boolean =>
    ['confirmed', 'discarded'].includes(offer.status);

const speakersForDirection = computed(() =>
    offerForm.value.direction === 'incoming' ? props.counterpartSpeakers : props.homeSpeakers,
);

const requestOptions = {
    preserveScroll: true,
    onStart: () => (processing.value = true),
    onFinish: () => (processing.value = false),
};

function copyPortalLink(): void {
    navigator.clipboard?.writeText(props.send.portal_url);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

function toggleOutline(id: string): void {
    const ids = offerForm.value.outline_ids;
    offerForm.value.outline_ids = ids.includes(id) ? ids.filter((item) => item !== id) : [...ids, id];
}

function startOfferFromMessage(message: MessageItem): void {
    showOfferForm.value = true;
    offerForm.value.source_message_id = message.id;
}

function submitOffer(): void {
    router.post(
        storeOffer([teamSlug.value, props.send.id]).url,
        {
            direction: offerForm.value.direction,
            speaker_id: offerForm.value.speaker_id,
            target_date: offerForm.value.target_date || null,
            outline_ids: offerForm.value.outline_ids,
            source_message_id: offerForm.value.source_message_id,
        },
        {
            ...requestOptions,
            onSuccess: () => {
                offerForm.value = {
                    direction: offerForm.value.direction,
                    speaker_id: '',
                    target_date: '',
                    outline_ids: [],
                    source_message_id: null,
                };
            },
        },
    );
}

function removeOffer(offer: OfferItem): void {
    router.delete(destroyOffer([teamSlug.value, props.send.id, offer.id]).url, requestOptions);
}

function setOutline(offer: OfferItem, outlineId: string): void {
    router.post(
        chooseOutline([teamSlug.value, props.send.id, offer.id]).url,
        { outline_id: outlineId },
        requestOptions,
    );
}

function accept(offer: OfferItem): void {
    router.post(acceptOffer([teamSlug.value, props.send.id, offer.id]).url, {}, requestOptions);
}

function decline(offer: OfferItem): void {
    router.post(declineOffer([teamSlug.value, props.send.id, offer.id]).url, {}, requestOptions);
}

function submitReply(): void {
    router.post(
        storeReply([teamSlug.value, props.send.id]).url,
        { body: replyBody.value },
        { ...requestOptions, onSuccess: () => (replyBody.value = '') },
    );
}

function confirmAll(): void {
    router.post(confirmSend([teamSlug.value, props.send.id]).url, {}, requestOptions);
}

function markDeclined(): void {
    router.post(declineSend([teamSlug.value, props.send.id]).url, {}, requestOptions);
}
</script>

<template>
    <Head :title="t('app.public_talks.exchange.workbench.title', { congregation: props.send.congregation.name })" />

    <PageContainer
        :title="t('app.public_talks.exchange.workbench.title', { congregation: props.send.congregation.name })"
        :description="t('app.public_talks.exchange.workbench.description')"
        :back-href="exchangeIndex(teamSlug).url"
        width="5xl"
    >
        <template #actions>
            <Button variant="outline" size="sm" data-test="copy-portal-link" @click="copyPortalLink">
                <Check v-if="copied" class="size-4" />
                <Copy v-else class="size-4" />
                {{ copied ? t('app.public_talks.exchange.workbench.copied') : t('app.public_talks.exchange.workbench.copy_portal_link') }}
            </Button>
            <Button
                v-if="props.canManage"
                variant="outline"
                size="sm"
                data-test="mark-declined"
                @click="markDeclined"
            >
                {{ t('app.public_talks.exchange.workbench.mark_declined') }}
            </Button>
            <Button
                v-if="props.canManage"
                size="sm"
                data-test="confirm-send"
                :disabled="processing || !canConfirm"
                @click="confirmAll"
            >
                {{ t('app.public_talks.exchange.workbench.confirm_send') }}
            </Button>
        </template>

        <div class="flex flex-col gap-6">
            <InputError :message="errors.offers" />
            <InputError :message="errors.outline" />

            <!-- Balance -->
            <div class="flex items-center gap-3 rounded-lg border p-3" data-test="balance">
                <Badge variant="secondary">
                    {{ t('app.public_talks.exchange.workbench.balance', { incoming: incomingCount, outgoing: outgoingCount }) }}
                </Badge>
                <span v-if="meetingInfo" class="text-sm text-muted-foreground">{{ meetingInfo }}</span>
            </div>

            <!-- Week groups -->
            <p v-if="props.offers.length === 0" class="text-sm text-muted-foreground">
                {{ t('app.public_talks.exchange.workbench.no_offers') }}
            </p>

            <div
                v-for="group in weekGroups"
                :key="group.date ?? 'none'"
                class="flex flex-col gap-3 rounded-lg border p-4"
                data-test="week-group"
            >
                <h2 class="text-sm font-medium">
                    {{
                        group.date
                            ? t('app.public_talks.exchange.portal.week_of', { date: weekLabel(group.date) })
                            : t('app.public_talks.exchange.workbench.week_placeholder')
                    }}
                </h2>

                <div class="grid gap-3 md:grid-cols-2">
                    <!-- Incoming side -->
                    <div class="flex flex-col gap-3">
                        <p class="text-xs font-medium text-muted-foreground uppercase">
                            {{ t('app.public_talks.exchange.workbench.incoming_title') }}
                        </p>
                        <div
                            v-for="offer in group.incoming"
                            :key="offer.id"
                            class="flex flex-col gap-2 rounded-md border p-3"
                            data-test="offer-card"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ offer.speaker.name }}</p>
                                    <p v-if="offer.speaker.phone" class="text-xs text-muted-foreground">
                                        {{ offer.speaker.phone }}
                                    </p>
                                </div>
                                <Badge v-if="statusBadge(offer)" :variant="statusBadge(offer)!.variant">
                                    {{ statusBadge(offer)!.label }}
                                </Badge>
                            </div>

                            <div v-if="offer.outlines.length > 0" class="flex flex-wrap gap-1">
                                <Badge v-for="outline in offer.outlines" :key="outline.id" variant="outline">
                                    {{ outline.number }}
                                </Badge>
                            </div>

                            <template v-if="props.canManage && !offerLocked(offer)">
                                <Select
                                    :model-value="offer.chosen_outline_id ?? undefined"
                                    @update:model-value="(value) => setOutline(offer, String(value))"
                                >
                                    <SelectTrigger class="w-full" data-test="offer-theme">
                                        <SelectValue :placeholder="t('app.public_talks.exchange.workbench.theme_placeholder')" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="outline in offer.outlines" :key="outline.id" :value="outline.id">
                                            {{ outlineLabel(outline) }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>

                                <div class="flex items-center gap-2">
                                    <Button
                                        v-if="offer.status !== 'accepted'"
                                        size="sm"
                                        data-test="accept-offer"
                                        :disabled="processing || offer.chosen_outline_id === null"
                                        @click="accept(offer)"
                                    >
                                        {{ t('app.public_talks.exchange.workbench.accept') }}
                                    </Button>
                                    <Button
                                        v-if="offer.status !== 'declined'"
                                        size="sm"
                                        variant="outline"
                                        data-test="decline-offer"
                                        :disabled="processing"
                                        @click="decline(offer)"
                                    >
                                        {{ t('app.public_talks.exchange.workbench.decline_offer') }}
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="ml-auto"
                                        data-test="delete-offer"
                                        :disabled="processing"
                                        @click="removeOffer(offer)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                                <p v-if="offer.chosen_outline_id === null" class="text-xs text-muted-foreground">
                                    {{ t('app.public_talks.exchange.workbench.theme_missing') }}
                                </p>
                            </template>
                            <p v-else class="text-sm text-muted-foreground">{{ chosenOutlineLabel(offer) }}</p>
                        </div>
                    </div>

                    <!-- Outgoing side -->
                    <div class="flex flex-col gap-3">
                        <p class="text-xs font-medium text-muted-foreground uppercase">
                            {{ t('app.public_talks.exchange.workbench.outgoing_title') }}
                        </p>
                        <div
                            v-for="offer in group.outgoing"
                            :key="offer.id"
                            class="flex flex-col gap-2 rounded-md border p-3"
                            data-test="offer-card"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <p class="truncate text-sm font-medium">{{ offer.speaker.name }}</p>
                                <Badge v-if="statusBadge(offer)" :variant="statusBadge(offer)!.variant">
                                    {{ statusBadge(offer)!.label }}
                                </Badge>
                            </div>

                            <p class="text-sm text-muted-foreground">
                                {{ t('app.public_talks.exchange.workbench.their_choice') }}: {{ chosenOutlineLabel(offer) }}
                            </p>
                            <p v-if="meetingInfo" class="text-xs text-muted-foreground">{{ meetingInfo }}</p>

                            <div v-if="props.canManage && !offerLocked(offer)" class="flex items-center gap-2">
                                <Button
                                    v-if="offer.status !== 'accepted'"
                                    size="sm"
                                    data-test="accept-offer"
                                    :disabled="processing"
                                    @click="accept(offer)"
                                >
                                    {{ t('app.public_talks.exchange.workbench.accept') }}
                                </Button>
                                <Button
                                    v-if="offer.status !== 'declined'"
                                    size="sm"
                                    variant="outline"
                                    data-test="decline-offer"
                                    :disabled="processing"
                                    @click="decline(offer)"
                                >
                                    {{ t('app.public_talks.exchange.workbench.decline_offer') }}
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="ml-auto"
                                    data-test="delete-offer"
                                    :disabled="processing"
                                    @click="removeOffer(offer)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Manual offer form -->
            <div v-if="props.canManage" class="flex flex-col gap-3 rounded-lg border p-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-medium">{{ t('app.public_talks.exchange.workbench.add_offer') }}</h2>
                    <Button variant="outline" size="sm" data-test="add-offer" @click="showOfferForm = !showOfferForm">
                        <Plus class="size-4" />
                    </Button>
                </div>

                <div v-if="showOfferForm" class="flex flex-col gap-3">
                    <Badge v-if="offerForm.source_message_id" variant="secondary" class="w-fit">
                        {{ t('app.public_talks.exchange.workbench.from_message_badge') }}
                        <button type="button" class="ml-1" @click="offerForm.source_message_id = null">
                            <X class="size-3" />
                        </button>
                    </Badge>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label>{{ t('app.public_talks.exchange.workbench.direction_title') }}</Label>
                            <Select v-model="offerForm.direction">
                                <SelectTrigger class="w-full" data-test="offer-direction">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="incoming">
                                        {{ t('app.public_talks.exchange.workbench.incoming_title') }}
                                    </SelectItem>
                                    <SelectItem value="outgoing">
                                        {{ t('app.public_talks.exchange.workbench.outgoing_title') }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid gap-2">
                            <Label>{{ t('app.public_talks.exchange.workbench.speaker_label') }}</Label>
                            <Select v-model="offerForm.speaker_id">
                                <SelectTrigger class="w-full" data-test="offer-speaker">
                                    <SelectValue :placeholder="t('app.public_talks.exchange.workbench.speaker_placeholder')" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="speaker in speakersForDirection" :key="speaker.id" :value="speaker.id">
                                        {{ speaker.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.speaker_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label>{{ t('app.public_talks.exchange.workbench.week_label') }}</Label>
                            <Input v-model="offerForm.target_date" type="date" data-test="offer-week" />
                            <InputError :message="errors.target_date" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ t('app.public_talks.exchange.workbench.outlines_label') }}</Label>
                        <div class="flex max-h-28 flex-wrap gap-1 overflow-y-auto">
                            <Badge
                                v-for="outline in props.outlines"
                                :key="outline.id"
                                :variant="offerForm.outline_ids.includes(outline.id) ? 'default' : 'outline'"
                                class="cursor-pointer"
                                @click="toggleOutline(outline.id)"
                            >
                                {{ outline.number }}
                            </Badge>
                        </div>
                        <InputError :message="errors.outline_ids" />
                    </div>

                    <Button
                        class="self-start"
                        data-test="save-offer"
                        :disabled="processing || !offerForm.speaker_id"
                        @click="submitOffer"
                    >
                        {{ t('app.public_talks.exchange.workbench.add_offer') }}
                    </Button>
                </div>
            </div>

            <!-- Messages -->
            <div class="flex flex-col gap-3 rounded-lg border p-4">
                <h2 class="text-sm font-medium">{{ t('app.public_talks.exchange.workbench.messages_title') }}</h2>

                <p v-if="props.messages.length === 0" class="text-sm text-muted-foreground">
                    {{ t('app.public_talks.exchange.workbench.messages_empty') }}
                </p>

                <div
                    v-for="message in props.messages"
                    :key="message.id"
                    class="flex flex-col gap-1 rounded-md border p-3"
                    :class="message.direction === 'outbound' ? 'bg-muted/40' : ''"
                    data-test="message-row"
                >
                    <p class="whitespace-pre-wrap text-sm">{{ message.body }}</p>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-muted-foreground">{{ message.created_at }}</span>
                        <Button
                            v-if="props.canManage && message.direction !== 'outbound'"
                            variant="ghost"
                            size="sm"
                            data-test="offer-from-message"
                            @click="startOfferFromMessage(message)"
                        >
                            <MessageSquarePlus class="size-4" />
                            {{ t('app.public_talks.exchange.workbench.from_message') }}
                        </Button>
                    </div>
                </div>

                <div v-if="props.canManage" class="flex flex-col gap-2">
                    <Label for="reply-body">{{ t('app.public_talks.exchange.workbench.reply_label') }}</Label>
                    <textarea
                        id="reply-body"
                        v-model="replyBody"
                        rows="3"
                        class="w-full rounded-md border p-3 text-sm"
                        :placeholder="t('app.public_talks.exchange.workbench.reply_placeholder')"
                        data-test="reply-body"
                    ></textarea>
                    <InputError :message="errors.body" />
                    <Button
                        class="self-start"
                        data-test="save-reply"
                        :disabled="processing || !replyBody.trim()"
                        @click="submitReply"
                    >
                        {{ t('app.public_talks.exchange.workbench.save_reply') }}
                    </Button>
                </div>
            </div>
        </div>
    </PageContainer>
</template>
