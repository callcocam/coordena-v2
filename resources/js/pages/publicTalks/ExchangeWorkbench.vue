<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Check, Copy, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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
import {
    destroy as destroyOffer,
    store as storeOffer,
} from '@/routes/public-talks/exchange/offers';
import {
    confirm as confirmSend,
    decline as declineSend,
} from '@/routes/public-talks/exchange/sends';
import { store as storeReply } from '@/routes/public-talks/exchange/sends/replies';

type OfferItem = {
    id: string;
    direction: 'incoming' | 'outgoing';
    status: string;
    target_date: string | null;
    speaker: { id: string; name: string };
    outlines: { id: string; number: number; title: string }[];
};

type SpeakerItem = {
    id: string;
    name: string;
    outline_ids: string[];
};

type Props = {
    month: string;
    send: {
        id: string;
        status: string;
        sent_at: string | null;
        answered_at: string | null;
        portal_url: string;
        congregation: { id: string; name: string };
    };
    offers: OfferItem[];
    messages: { id: string; direction: string; body: string; created_at: string | null }[];
    openWeeks: { id: string; date: string }[];
    counterpartSpeakers: SpeakerItem[];
    homeSpeakers: SpeakerItem[];
    outlines: { id: string; number: number; title: string }[];
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
const copied = ref(false);

const replyBody = ref('');
const selectedOfferIds = ref<string[]>([]);

const offerForm = ref({
    direction: 'incoming' as 'incoming' | 'outgoing',
    speaker_id: '',
    target_date: '',
    outline_ids: [] as string[],
});

const speakersForDirection = computed(() =>
    offerForm.value.direction === 'incoming' ? props.counterpartSpeakers : props.homeSpeakers,
);

const weekLabel = (date: string): string =>
    new Date(`${date}T12:00:00`).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
    });

const copyPortalLink = async () => {
    await navigator.clipboard.writeText(props.send.portal_url);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
};

const requestOptions = {
    preserveScroll: true,
    onStart: () => (processing.value = true),
    onFinish: () => (processing.value = false),
    onError: (formErrors: Record<string, string>) => (errors.value = formErrors),
    onSuccess: () => (errors.value = {}),
};

const saveReply = () => {
    router.post(
        storeReply([teamSlug.value, props.send.id]).url,
        { body: replyBody.value },
        {
            ...requestOptions,
            onSuccess: () => {
                errors.value = {};
                replyBody.value = '';
            },
        },
    );
};

const addOffer = () => {
    router.post(
        storeOffer([teamSlug.value, props.send.id]).url,
        {
            direction: offerForm.value.direction,
            speaker_id: offerForm.value.speaker_id,
            target_date: offerForm.value.target_date || null,
            outline_ids: offerForm.value.outline_ids,
        },
        {
            ...requestOptions,
            onSuccess: () => {
                errors.value = {};
                offerForm.value = {
                    direction: offerForm.value.direction,
                    speaker_id: '',
                    target_date: '',
                    outline_ids: [],
                };
            },
        },
    );
};

const removeOffer = (offer: OfferItem) => {
    router.delete(destroyOffer([teamSlug.value, props.send.id, offer.id]).url, requestOptions);
};

const toggleOfferSelection = (offer: OfferItem) => {
    selectedOfferIds.value = selectedOfferIds.value.includes(offer.id)
        ? selectedOfferIds.value.filter((id) => id !== offer.id)
        : [...selectedOfferIds.value, offer.id];
};

const confirmSelected = () => {
    router.post(
        confirmSend([teamSlug.value, props.send.id]).url,
        { offers: selectedOfferIds.value },
        {
            ...requestOptions,
            onSuccess: () => {
                errors.value = {};
                selectedOfferIds.value = [];
            },
        },
    );
};

const markDeclined = () => {
    router.post(declineSend([teamSlug.value, props.send.id]).url, {}, requestOptions);
};

const offersFor = (direction: 'incoming' | 'outgoing') =>
    props.offers.filter((offer) => offer.direction === direction);

const toggleOutline = (outlineId: string) => {
    offerForm.value.outline_ids = offerForm.value.outline_ids.includes(outlineId)
        ? offerForm.value.outline_ids.filter((id) => id !== outlineId)
        : [...offerForm.value.outline_ids, outlineId];
};
</script>

<template>
    <Head :title="t('app.public_talks.exchange.workbench.title', { congregation: props.send.congregation.name })" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <Heading
                :title="t('app.public_talks.exchange.workbench.title', { congregation: props.send.congregation.name })"
                :description="t('app.public_talks.exchange.workbench.description')"
            />

            <div class="flex items-center gap-2">
                <Badge variant="secondary">
                    {{ t(`app.public_talks.exchange.send_statuses.${props.send.status}`) }}
                </Badge>

                <Button variant="outline" size="sm" data-test="copy-portal-link" @click="copyPortalLink">
                    <Check v-if="copied" class="size-4" />
                    <Copy v-else class="size-4" />
                    {{ t('app.public_talks.exchange.portal_link') }}
                </Button>

                <Button
                    v-if="props.canManage"
                    variant="destructive"
                    size="sm"
                    data-test="mark-declined"
                    @click="markDeclined"
                >
                    {{ t('app.public_talks.exchange.mark_declined') }}
                </Button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="flex flex-col gap-6">
                <div
                    v-for="direction in (['incoming', 'outgoing'] as const)"
                    :key="direction"
                    class="flex flex-col gap-3 rounded-lg border p-4"
                >
                    <h2 class="text-sm font-medium">
                        {{ t(`app.public_talks.exchange.workbench.${direction}_title`) }}
                    </h2>

                    <p
                        v-if="offersFor(direction).length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        {{ t('app.public_talks.exchange.workbench.no_offers') }}
                    </p>

                    <div
                        v-for="offer in offersFor(direction)"
                        :key="offer.id"
                        class="flex items-center justify-between gap-3 rounded-md border p-3"
                        data-test="offer-row"
                    >
                        <label class="flex flex-1 items-center gap-3">
                            <input
                                v-if="props.canManage && offer.status === 'draft'"
                                type="checkbox"
                                :checked="selectedOfferIds.includes(offer.id)"
                                class="size-4"
                                data-test="offer-select"
                                @change="toggleOfferSelection(offer)"
                            />
                            <span class="flex flex-col">
                                <span class="text-sm font-medium">{{ offer.speaker.name }}</span>
                                <span class="text-xs text-muted-foreground">
                                    <template v-if="offer.target_date">{{ weekLabel(offer.target_date) }}</template>
                                    <template v-else>{{ t('app.public_talks.exchange.workbench.week_placeholder') }}</template>
                                    <template v-if="offer.outlines.length > 0">
                                        · {{ offer.outlines.map((outline) => outline.number).join(', ') }}
                                    </template>
                                </span>
                            </span>
                        </label>

                        <div class="flex items-center gap-2">
                            <Badge v-if="offer.status === 'confirmed'">
                                {{ t('app.public_talks.exchange.workbench.confirmed_badge') }}
                            </Badge>
                            <Badge v-else-if="offer.status === 'discarded'" variant="destructive">
                                {{ t('app.public_talks.exchange.workbench.discarded_badge') }}
                            </Badge>

                            <Button
                                v-if="props.canManage && offer.status === 'draft'"
                                variant="ghost"
                                size="icon"
                                data-test="delete-offer"
                                @click="removeOffer(offer)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>

                <div v-if="props.canManage" class="flex flex-col gap-3 rounded-lg border p-4">
                    <h2 class="text-sm font-medium">
                        {{ t('app.public_talks.exchange.workbench.add_offer') }}
                    </h2>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label>{{ t('app.public_talks.exchange.workbench.incoming_title') }}</Label>
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
                                    <SelectValue
                                        :placeholder="t('app.public_talks.exchange.workbench.speaker_placeholder')"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="speaker in speakersForDirection"
                                        :key="speaker.id"
                                        :value="speaker.id"
                                    >
                                        {{ speaker.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.speaker_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label>{{ t('app.public_talks.exchange.workbench.week_label') }}</Label>
                            <Select v-model="offerForm.target_date">
                                <SelectTrigger class="w-full" data-test="offer-week">
                                    <SelectValue
                                        :placeholder="t('app.public_talks.exchange.workbench.week_placeholder')"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="week in props.openWeeks"
                                        :key="week.id"
                                        :value="week.date"
                                    >
                                        {{ weekLabel(week.date) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.target_date" />
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
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <Button
                            :disabled="processing || !offerForm.speaker_id"
                            data-test="add-offer"
                            @click="addOffer"
                        >
                            <Plus class="size-4" />
                            {{ t('app.public_talks.exchange.workbench.add_offer') }}
                        </Button>

                        <Button
                            variant="secondary"
                            :disabled="processing || selectedOfferIds.length === 0"
                            data-test="confirm-selected"
                            @click="confirmSelected"
                        >
                            {{ t('app.public_talks.exchange.workbench.confirm_selected') }}
                        </Button>
                    </div>

                    <InputError :message="errors.offers" />
                </div>
            </div>

            <div class="flex flex-col gap-6">
                <div class="flex flex-col gap-3 rounded-lg border p-4">
                    <h2 class="text-sm font-medium">
                        {{ t('app.public_talks.exchange.workbench.messages_title') }}
                    </h2>

                    <p
                        v-if="props.messages.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        {{ t('app.public_talks.exchange.workbench.messages_empty') }}
                    </p>

                    <div
                        v-for="message in props.messages"
                        :key="message.id"
                        class="rounded-md border p-3"
                        :class="message.direction === 'outbound' ? 'bg-muted/40' : ''"
                        data-test="message-row"
                    >
                        <p class="whitespace-pre-wrap text-xs">{{ message.body }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ message.created_at }}</p>
                    </div>
                </div>

                <div v-if="props.canManage" class="flex flex-col gap-3 rounded-lg border p-4">
                    <Label for="reply-body">
                        {{ t('app.public_talks.exchange.workbench.reply_label') }}
                    </Label>
                    <textarea
                        id="reply-body"
                        v-model="replyBody"
                        rows="5"
                        class="w-full rounded-md border p-3 text-sm"
                        :placeholder="t('app.public_talks.exchange.workbench.reply_placeholder')"
                        data-test="reply-body"
                    ></textarea>
                    <InputError :message="errors.body" />

                    <Button
                        class="self-start"
                        :disabled="processing || replyBody.trim() === ''"
                        data-test="save-reply"
                        @click="saveReply"
                    >
                        {{ t('app.public_talks.exchange.workbench.save_reply') }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
