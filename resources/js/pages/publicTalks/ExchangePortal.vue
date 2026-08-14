<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, Send, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
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
import { submit as submitPortal } from '@/routes/exchange/portal';

type OfferRow = {
    speaker_name: string;
    phone: string;
    outline_number: string;
    date: string;
};

type Props = {
    token: string;
    month: string;
    homeCongregation: string | null;
    invitedCongregation: string;
    closed: boolean;
    openWeeks: { date: string }[];
    homeSpeakers: { name: string; outlines: string[] }[];
};

const props = defineProps<Props>();

const { t } = useT();

const submitted = ref(false);

const emptyRow = (): OfferRow => ({
    speaker_name: '',
    phone: '',
    outline_number: '',
    date: props.openWeeks[0]?.date ?? '',
});

const form = useForm({
    offers: [emptyRow()] as OfferRow[],
});

const monthLabel = (): string => {
    const [year, monthNumber] = props.month.split('-');

    return new Date(Number(year), Number(monthNumber) - 1, 1).toLocaleDateString('pt-BR', {
        month: 'long',
        year: 'numeric',
    });
};

const weekLabel = (date: string): string =>
    new Date(`${date}T12:00:00`).toLocaleDateString('pt-BR', {
        weekday: 'short',
        day: '2-digit',
        month: '2-digit',
    });

const addRow = () => {
    form.offers.push(emptyRow());
};

const removeRow = (index: number) => {
    form.offers.splice(index, 1);
};

const submit = () => {
    form.transform((data) => ({
        offers: data.offers.map((row) => ({
            speaker_name: row.speaker_name,
            phone: row.phone || null,
            outline_number: row.outline_number === '' ? null : Number(row.outline_number),
            date: row.date,
        })),
    })).post(submitPortal(props.token).url, {
        preserveScroll: true,
        onSuccess: () => (submitted.value = true),
    });
};
</script>

<template>
    <Head :title="t('app.public_talks.exchange.portal.title', { home: props.homeCongregation ?? '' })" />

    <div class="mx-auto flex min-h-svh w-full max-w-3xl flex-col gap-6 p-4 py-10">
        <div class="flex flex-col gap-2 text-center">
            <h1 class="text-xl font-semibold">
                {{ t('app.public_talks.exchange.portal.title', { home: props.homeCongregation ?? '' }) }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ t('app.public_talks.exchange.portal.description', { month: monthLabel() }) }}
            </p>
        </div>

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
                {{ t('app.public_talks.exchange.portal.thanks_description', { home: props.homeCongregation ?? '' }) }}
            </p>
        </div>

        <template v-else>
            <div
                v-if="props.homeSpeakers.length > 0"
                class="flex flex-col gap-2 rounded-lg border p-4"
            >
                <h2 class="text-sm font-medium">
                    {{ t('app.public_talks.exchange.portal.our_speakers') }}
                </h2>
                <ul class="grid gap-1 text-sm text-muted-foreground sm:grid-cols-2">
                    <li v-for="speaker in props.homeSpeakers" :key="speaker.name">
                        {{ speaker.name }}
                        <span v-if="speaker.outlines.length > 0" class="text-xs">
                            — {{ speaker.outlines.join('; ') }}
                        </span>
                    </li>
                </ul>
            </div>

            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div
                    v-for="(row, index) in form.offers"
                    :key="index"
                    class="flex flex-col gap-3 rounded-lg border p-4"
                    data-test="portal-row"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium">
                            {{ t('app.public_talks.exchange.portal.speaker_number', { number: index + 1 }) }}
                        </span>
                        <Button
                            v-if="form.offers.length > 1"
                            type="button"
                            variant="ghost"
                            size="icon"
                            data-test="remove-row"
                            @click="removeRow(index)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label :for="`speaker-name-${index}`">
                                {{ t('app.public_talks.exchange.portal.speaker_name_label') }}
                            </Label>
                            <Input
                                :id="`speaker-name-${index}`"
                                v-model="row.speaker_name"
                                required
                            />
                            <InputError :message="form.errors[`offers.${index}.speaker_name`]" />
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`phone-${index}`">
                                {{ t('app.public_talks.exchange.portal.phone_label') }}
                            </Label>
                            <Input :id="`phone-${index}`" v-model="row.phone" />
                            <InputError :message="form.errors[`offers.${index}.phone`]" />
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`outline-${index}`">
                                {{ t('app.public_talks.exchange.portal.outline_label') }}
                            </Label>
                            <Input
                                :id="`outline-${index}`"
                                v-model="row.outline_number"
                                type="number"
                                min="1"
                            />
                            <InputError :message="form.errors[`offers.${index}.outline_number`]" />
                        </div>

                        <div class="grid gap-2">
                            <Label>{{ t('app.public_talks.exchange.portal.week_label') }}</Label>
                            <Select v-model="row.date">
                                <SelectTrigger class="w-full" :data-test="`portal-date-${index}`">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="week in props.openWeeks"
                                        :key="week.date"
                                        :value="week.date"
                                    >
                                        {{ weekLabel(week.date) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors[`offers.${index}.date`]" />
                        </div>
                    </div>
                </div>

                <InputError :message="form.errors.offers" />

                <div class="flex justify-between">
                    <Button type="button" variant="outline" data-test="add-row" @click="addRow">
                        <Plus class="size-4" />
                        {{ t('app.public_talks.exchange.portal.add_row') }}
                    </Button>

                    <Button type="submit" :disabled="form.processing" data-test="portal-submit">
                        <Send class="size-4" />
                        {{ t('app.public_talks.exchange.portal.submit') }}
                    </Button>
                </div>
            </form>
        </template>
    </div>
</template>
