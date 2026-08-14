<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Check, Copy, Send } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
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
import { index as exchangeIndex } from '@/routes/public-talks/exchange';
import { show as showSend, store as storeSend } from '@/routes/public-talks/exchange/sends';

type Congregation = {
    id: string;
    name: string;
    city: string | null;
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
    selectedId: string | null;
    composeText: string | null;
    sends: SendItem[];
    canSend: boolean;
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

const changeMonth = (month: unknown) => {
    router.get(
        exchangeIndex(teamSlug.value).url,
        { month: String(month) },
        { preserveScroll: true },
    );
};

const changeCongregation = (congregationId: unknown) => {
    router.get(
        exchangeIndex(teamSlug.value).url,
        { month: props.month, congregation: String(congregationId) },
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
        { congregation_id: props.selectedId, month: props.month },
        {
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
        },
    );
};

const sendStatusVariant = (status: string) => {
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

    <div class="flex flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <Heading
                :title="t('app.public_talks.exchange.title')"
                :description="t('app.public_talks.exchange.description')"
            />

            <div class="flex items-center gap-2">
                <span class="text-sm text-muted-foreground">
                    {{ t('app.public_talks.exchange.month_label') }}
                </span>
                <Select :model-value="props.month" @update:model-value="changeMonth">
                    <SelectTrigger class="w-44" data-test="month-select">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="month in props.months" :key="month" :value="month">
                            <span class="capitalize">{{ monthLabel(month) }}</span>
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="flex flex-col gap-4 rounded-lg border p-4">
                <h2 class="text-sm font-medium">
                    {{ t('app.public_talks.exchange.open_weeks') }}
                </h2>

                <p
                    v-if="props.openWeeks.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{ t('app.public_talks.exchange.no_open_weeks') }}
                </p>

                <div v-else class="flex flex-wrap gap-2">
                    <Badge
                        v-for="week in props.openWeeks"
                        :key="week.id"
                        variant="outline"
                    >
                        {{ weekLabel(week.date) }}
                    </Badge>
                </div>

                <template v-if="props.canSend">
                    <h2 class="mt-2 text-sm font-medium">
                        {{ t('app.public_talks.exchange.send_to') }}
                    </h2>

                    <Select
                        :model-value="props.selectedId ?? undefined"
                        @update:model-value="changeCongregation"
                    >
                        <SelectTrigger class="w-full" data-test="congregation-select">
                            <SelectValue
                                :placeholder="t('app.public_talks.exchange.congregation_placeholder')"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="candidate in props.candidates"
                                :key="candidate.id"
                                :value="candidate.id"
                            >
                                {{ candidate.name }}
                                <span v-if="candidate.city" class="text-muted-foreground">
                                    — {{ candidate.city }}
                                </span>
                                <Badge
                                    v-if="candidate.id === props.suggestionId"
                                    variant="secondary"
                                    class="ml-2"
                                >
                                    {{ t('app.public_talks.exchange.suggestion') }}
                                </Badge>
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <p
                        v-if="props.candidates.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        {{ t('app.public_talks.exchange.no_suggestion') }}
                    </p>

                    <template v-if="props.composeText">
                        <textarea
                            :value="props.composeText"
                            readonly
                            rows="10"
                            class="w-full rounded-md border bg-muted/40 p-3 font-mono text-xs"
                            data-test="compose-text"
                        ></textarea>

                        <div class="flex gap-2">
                            <Button variant="outline" data-test="copy-text" @click="copyText">
                                <Check v-if="copied" class="size-4" />
                                <Copy v-else class="size-4" />
                                {{ copied ? t('app.public_talks.exchange.copied') : t('app.public_talks.exchange.copy') }}
                            </Button>

                            <Button
                                :disabled="processing"
                                data-test="mark-sent"
                                @click="markSent"
                            >
                                <Send class="size-4" />
                                {{ t('app.public_talks.exchange.mark_sent') }}
                            </Button>
                        </div>
                    </template>
                </template>
            </div>

            <div class="flex flex-col gap-4 rounded-lg border p-4">
                <h2 class="text-sm font-medium">
                    {{ t('app.public_talks.exchange.sends_title') }}
                </h2>

                <p v-if="props.sends.length === 0" class="text-sm text-muted-foreground">
                    {{ t('app.public_talks.exchange.sends_empty') }}
                </p>

                <div
                    v-for="send in props.sends"
                    :key="send.id"
                    class="flex items-center justify-between gap-3 rounded-md border p-3"
                    data-test="send-row"
                >
                    <div class="flex flex-col gap-1">
                        <span class="text-sm font-medium">{{ send.congregation.name }}</span>
                        <span class="text-xs text-muted-foreground">
                            <template v-if="send.answered_at">
                                {{ t('app.public_talks.exchange.answered_at', { date: send.answered_at }) }}
                            </template>
                            <template v-else-if="send.sent_at">
                                {{ t('app.public_talks.exchange.sent_at', { date: send.sent_at }) }}
                            </template>
                        </span>
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
                </div>
            </div>
        </div>
    </div>
</template>
