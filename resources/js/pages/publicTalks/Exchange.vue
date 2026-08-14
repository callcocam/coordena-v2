<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Check, Copy, MessageCircle, Send } from '@lucide/vue';
import { computed, ref } from 'vue';
import PageContainer from '@/components/PageContainer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { schedule } from '@/routes/public-talks';
import { show as congregationShow } from '@/routes/acervo/congregations';
import { index as exchangeIndex } from '@/routes/public-talks/exchange';
import { show as showSend, store as storeSend } from '@/routes/public-talks/exchange/sends';

type Congregation = {
    id: string;
    name: string;
    city: string | null;
    has_whatsapp: boolean;
    last_invited_at: string | null;
};

type SendItem = {
    id: string;
    status: string;
    sent_at: string | null;
    answered_at: string | null;
    offers_count: number;
    congregation: { id: string; name: string };
};

type Props = {
    month: string;
    months: string[];
    invite: { id: string; status: string };
    openWeeks: { id: string; date: string }[];
    suggestionId: string | null;
    candidates: Congregation[];
    pendingIntro: { id: string; name: string; city: string | null }[];
    selectedId: string | null;
    composeText: string | null;
    sends: SendItem[];
    canSend: boolean;
    whatsappEnabled: boolean;
    selectedHasWhatsapp: boolean;
};

const props = defineProps<Props>();

defineOptions({
    layout: AppLayout,
});

const { t } = useT();
const page = usePage();

const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const processing = ref(false);
const copied = ref(false);
const channel = ref<'manual' | 'whatsapp'>('manual');

const selectedCongregation = computed(
    () => props.candidates.find((candidate) => candidate.id === props.selectedId) ?? null,
);

const rotationFilter = ref('');

const normalize = (value: string): string =>
    value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();

const filteredCandidates = computed(() => {
    const term = normalize(rotationFilter.value.trim());

    if (term === '') {
        return props.candidates;
    }

    return props.candidates.filter((candidate) =>
        normalize(`${candidate.name} ${candidate.city ?? ''}`).includes(term),
    );
});

const whatsappAvailable = computed(
    () => props.whatsappEnabled && props.selectedHasWhatsapp,
);

const monthLabel = (month: string): string => {
    const [year, monthNumber] = month.split('-');

    return new Date(Number(year), Number(monthNumber) - 1, 1).toLocaleDateString('pt-BR', {
        month: 'long',
        year: 'numeric',
    });
};

const weekLabel = (date: string): string =>
    new Date(`${date}T12:00:00`).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
    });

const dateTimeLabel = (value: string): string =>
    new Date(value.replace(' ', 'T')).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });

const changeMonth = (month: unknown) => {
    router.get(
        exchangeIndex(teamSlug.value).url,
        { month: String(month) },
        { preserveScroll: true },
    );
};

const changeCongregation = (congregationId: string) => {
    router.get(
        exchangeIndex(teamSlug.value).url,
        { month: props.month, congregation: congregationId },
        { preserveScroll: true },
    );
};

const copyText = async () => {
    if (!props.composeText) {
        return;
    }

    await navigator.clipboard.writeText(props.composeText);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
};

const markSent = () => {
    if (!props.selectedId) {
        return;
    }

    router.post(
        storeSend(teamSlug.value).url,
        {
            month: props.month,
            congregation_id: props.selectedId,
            channel: channel.value,
        },
        {
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
        },
    );
};

const sendStatusVariant = (status: string): 'default' | 'destructive' | 'secondary' => {
    if (status === 'answered') {
        return 'default';
    }

    if (status === 'declined' || status === 'expired') {
        return 'destructive';
    }

    return 'secondary';
};
</script>

<template>
    <Head :title="t('app.public_talks.exchange.title')" />

    <PageContainer
        :title="t('app.public_talks.exchange.title')"
        :description="t('app.public_talks.exchange.description')"
        :back-href="schedule(teamSlug)"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="text-sm text-muted-foreground">
                    {{ t('app.public_talks.exchange.month_label') }}
                </span>
                <Select :model-value="props.month" @update:model-value="changeMonth">
                    <SelectTrigger class="w-44" data-test="month-select">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="option in props.months" :key="option" :value="option">
                            <span class="capitalize">{{ monthLabel(option) }}</span>
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <Badge variant="outline">{{ t(`app.public_talks.exchange.statuses.${props.invite.status}`) }}</Badge>
        </div>

        <section class="rounded-lg border p-4">
            <h2 class="text-sm font-medium">{{ t('app.public_talks.exchange.open_weeks') }}</h2>
            <div v-if="props.openWeeks.length" class="mt-2 flex flex-wrap gap-2">
                <Badge v-for="week in props.openWeeks" :key="week.id" variant="secondary">
                    {{ weekLabel(week.date) }}
                </Badge>
            </div>
            <p v-else class="mt-2 text-sm text-muted-foreground">
                {{ t('app.public_talks.exchange.no_open_weeks') }}
            </p>
        </section>

        <section class="rounded-lg border">
            <div class="border-b p-4">
                <h2 class="text-sm font-medium">{{ t('app.public_talks.exchange.rotation_title') }}</h2>
                <p class="text-xs text-muted-foreground">
                    {{ t('app.public_talks.exchange.rotation_description') }}
                </p>
            </div>
            <div v-if="props.candidates.length > 0" class="border-b p-3">
                <input
                    v-model="rotationFilter"
                    type="search"
                    :placeholder="t('app.public_talks.exchange.rotation_filter_placeholder')"
                    class="w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
                    data-test="rotation-filter"
                />
            </div>
            <div v-if="props.candidates.length === 0" data-test="rotation-empty">
                <p class="p-4 text-sm text-muted-foreground">
                    {{ t('app.public_talks.exchange.no_suggestion') }}
                </p>
                <section
                    v-if="props.pendingIntro.length > 0"
                    class="border-t"
                    data-test="pending-intro"
                >
                    <div class="p-4 pb-2">
                        <h3 class="text-sm font-medium">
                            {{ t('app.public_talks.exchange.pending_intro_title') }}
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            {{ t('app.public_talks.exchange.pending_intro_description') }}
                        </p>
                    </div>
                    <ul class="max-h-64 overflow-y-auto overscroll-contain">
                        <li v-for="pending in props.pendingIntro" :key="pending.id">
                            <Link
                                :href="congregationShow([teamSlug, pending.id]).url"
                                class="flex w-full items-center gap-3 border-b p-4 transition-colors last:border-b-0 hover:bg-accent"
                                :data-test="`pending-intro-${pending.id}`"
                            >
                                <div class="min-w-0 flex-1">
                                    <span class="block truncate font-medium">{{ pending.name }}</span>
                                    <span v-if="pending.city" class="text-xs text-muted-foreground">
                                        {{ pending.city }}
                                    </span>
                                </div>
                            </Link>
                        </li>
                    </ul>
                </section>
            </div>
            <p
                v-else-if="filteredCandidates.length === 0"
                class="p-4 text-sm text-muted-foreground"
                data-test="rotation-filter-empty"
            >
                {{ t('app.public_talks.exchange.rotation_filter_empty') }}
            </p>
            <ul v-else class="max-h-96 overflow-y-auto overscroll-contain" data-test="rotation-list">
                <li v-for="candidate in filteredCandidates" :key="candidate.id">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 border-b p-4 text-left transition-colors last:border-b-0 hover:bg-accent"
                        :class="candidate.id === props.selectedId ? 'bg-accent' : ''"
                        :data-test="`rotation-item-${candidate.id}`"
                        @click="changeCongregation(candidate.id)"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="truncate font-medium">{{ candidate.name }}</span>
                                <Badge v-if="candidate.id === props.suggestionId" data-test="next-badge">
                                    {{ t('app.public_talks.exchange.next_badge') }}
                                </Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                <span v-if="candidate.city">{{ candidate.city }} · </span>
                                <span v-if="candidate.last_invited_at">
                                    {{ t('app.public_talks.exchange.last_invited', { date: dateTimeLabel(candidate.last_invited_at) }) }}
                                </span>
                                <span v-else>{{ t('app.public_talks.exchange.never_invited') }}</span>
                            </p>
                        </div>
                        <MessageCircle
                            v-if="candidate.has_whatsapp"
                            class="size-4 shrink-0 text-muted-foreground"
                            :aria-label="t('app.public_talks.exchange.channel_whatsapp')"
                        />
                        <span v-else class="shrink-0 text-xs text-muted-foreground">
                            {{ t('app.public_talks.exchange.no_whatsapp') }}
                        </span>
                        <Check
                            v-if="candidate.id === props.selectedId"
                            class="size-4 shrink-0"
                            data-test="selected-check"
                        />
                    </button>
                </li>
            </ul>
        </section>

        <section v-if="selectedCongregation && props.composeText" class="flex flex-col gap-3 rounded-lg border p-4">
            <h2 class="text-sm font-medium">
                {{ t('app.public_talks.exchange.send_to') }}: {{ selectedCongregation.name }}
            </h2>
            <textarea
                :value="props.composeText"
                readonly
                rows="10"
                class="w-full rounded-md border bg-muted/40 p-3 font-mono text-xs"
                data-test="compose-text"
            />
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    :variant="channel === 'manual' ? 'default' : 'outline'"
                    size="sm"
                    data-test="channel-manual"
                    @click="channel = 'manual'"
                >
                    {{ t('app.public_talks.exchange.channel_manual') }}
                </Button>
                <Button
                    :variant="channel === 'whatsapp' ? 'default' : 'outline'"
                    size="sm"
                    :disabled="!whatsappAvailable"
                    data-test="channel-whatsapp"
                    @click="channel = 'whatsapp'"
                >
                    {{ t('app.public_talks.exchange.channel_whatsapp') }}
                </Button>
                <span v-if="!whatsappAvailable" class="text-xs text-muted-foreground">
                    {{ t('app.public_talks.exchange.channel_whatsapp_unavailable') }}
                </span>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" data-test="copy-text" @click="copyText">
                    <Check v-if="copied" class="size-4" />
                    <Copy v-else class="size-4" />
                    {{ copied ? t('app.public_talks.exchange.copied') : t('app.public_talks.exchange.copy') }}
                </Button>
                <Button
                    v-if="props.canSend"
                    :disabled="processing"
                    data-test="mark-sent"
                    @click="markSent"
                >
                    <Send class="size-4" />
                    {{
                        channel === 'whatsapp'
                            ? t('app.public_talks.exchange.send_whatsapp')
                            : t('app.public_talks.exchange.mark_sent')
                    }}
                </Button>
            </div>
        </section>

        <section class="rounded-lg border">
            <h2 class="border-b p-4 text-sm font-medium">
                {{ t('app.public_talks.exchange.sends_title') }}
            </h2>
            <p v-if="props.sends.length === 0" class="p-4 text-sm text-muted-foreground">
                {{ t('app.public_talks.exchange.sends_empty') }}
            </p>
            <ul v-else>
                <li
                    v-for="send in props.sends"
                    :key="send.id"
                    class="flex flex-wrap items-center justify-between gap-3 border-b p-4 last:border-b-0"
                >
                    <div class="min-w-0">
                        <span class="font-medium">{{ send.congregation.name }}</span>
                        <p class="text-xs text-muted-foreground">
                            <template v-if="send.sent_at">
                                {{ t('app.public_talks.exchange.sent_at', { date: send.sent_at }) }}
                            </template>
                            <template v-if="send.answered_at">
                                · {{ t('app.public_talks.exchange.answered_at', { date: send.answered_at }) }}
                            </template>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge :variant="sendStatusVariant(send.status)">
                            {{ t(`app.public_talks.exchange.send_statuses.${send.status}`) }}
                        </Badge>
                        <Button
                            variant="outline"
                            size="sm"
                            data-test="open-workbench"
                            @click="router.get(showSend([teamSlug, send.id]).url)"
                        >
                            {{ t('app.public_talks.exchange.open_workbench') }}
                        </Button>
                    </div>
                </li>
            </ul>
        </section>
    </PageContainer>
</template>
