<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { update as updateCongregation } from '@/routes/acervo/congregations';
import {
    destroy as destroySpeaker,
    store as storeSpeaker,
    update as updateSpeaker,
} from '@/routes/acervo/speakers';
import type {
    CongregationDetail,
    OutlineOption,
    SpeakerDetail,
    SpeakerRole,
} from '@/types';

type Props = {
    congregation: CongregationDetail;
    speakers: SpeakerDetail[];
    outlines: OutlineOption[];
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

const roles: SpeakerRole[] = ['elder', 'ministerial_servant', 'other'];

/* Congregation edit dialog */
const editOpen = ref(false);

const congregationFields = () => ({
    name: props.congregation.name,
    city: props.congregation.city ?? '',
    circuit: props.congregation.circuit ?? '',
    address: props.congregation.address ?? '',
    contact_name: props.congregation.contact_name ?? '',
    contact_phone: props.congregation.contact_phone ?? '',
    contact_email: props.congregation.contact_email ?? '',
    secretary_name: props.congregation.secretary_name ?? '',
    secretary_phone: props.congregation.secretary_phone ?? '',
    secretary_email: props.congregation.secretary_email ?? '',
    meeting_weekday: props.congregation.meeting_weekday,
    meeting_time: props.congregation.meeting_time ?? '',
    exchange_opt: props.congregation.exchange_opt,
});

const congregationForm = ref(congregationFields());

const openEdit = () => {
    congregationForm.value = congregationFields();
    errors.value = {};
    editOpen.value = true;
};

const submitCongregation = () => {
    router.put(
        updateCongregation([teamSlug.value, props.congregation.id]).url,
        {
            ...congregationForm.value,
            city: congregationForm.value.city || null,
            circuit: congregationForm.value.circuit || null,
            address: congregationForm.value.address || null,
            contact_name: congregationForm.value.contact_name || null,
            contact_phone: congregationForm.value.contact_phone || null,
            contact_email: congregationForm.value.contact_email || null,
            secretary_name: congregationForm.value.secretary_name || null,
            secretary_phone: congregationForm.value.secretary_phone || null,
            secretary_email: congregationForm.value.secretary_email || null,
            meeting_time: congregationForm.value.meeting_time || null,
        },
        {
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
            onError: (formErrors) => (errors.value = formErrors),
            onSuccess: () => (editOpen.value = false),
        },
    );
};

/* Speaker dialog (create/edit) */
const speakerOpen = ref(false);
const editingSpeaker = ref<SpeakerDetail | null>(null);
const speakerForm = ref({
    name: '',
    role: 'elder' as SpeakerRole,
    phone: '',
    is_active: true,
    notes: '',
    outline_ids: [] as string[],
});

const openSpeaker = (speaker: SpeakerDetail | null) => {
    editingSpeaker.value = speaker;
    speakerForm.value = speaker
        ? {
              name: speaker.name,
              role: speaker.role,
              phone: speaker.phone ?? '',
              is_active: speaker.is_active,
              notes: speaker.notes ?? '',
              outline_ids: [...speaker.outline_ids],
          }
        : {
              name: '',
              role: 'elder',
              phone: '',
              is_active: true,
              notes: '',
              outline_ids: [],
          };
    errors.value = {};
    speakerOpen.value = true;
};

const toggleOutline = (outlineId: string) => {
    const ids = speakerForm.value.outline_ids;
    const index = ids.indexOf(outlineId);

    if (index === -1) {
        ids.push(outlineId);
    } else {
        ids.splice(index, 1);
    }
};

const submitSpeaker = () => {
    const payload = {
        name: speakerForm.value.name,
        role: speakerForm.value.role,
        phone: speakerForm.value.phone || null,
        is_active: speakerForm.value.is_active,
        notes: speakerForm.value.notes || null,
        outline_ids: speakerForm.value.outline_ids,
    };

    const options = {
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
        onError: (formErrors: Record<string, string>) =>
            (errors.value = formErrors),
        onSuccess: () => (speakerOpen.value = false),
    };

    if (editingSpeaker.value) {
        router.put(
            updateSpeaker([
                teamSlug.value,
                props.congregation.id,
                editingSpeaker.value.id,
            ]).url,
            payload,
            options,
        );

        return;
    }

    router.post(
        storeSpeaker([teamSlug.value, props.congregation.id]).url,
        payload,
        options,
    );
};

const removeSpeaker = (speaker: SpeakerDetail) => {
    router.delete(
        destroySpeaker([teamSlug.value, props.congregation.id, speaker.id])
            .url,
        { preserveScroll: true },
    );
};

const roleLabel = (role: SpeakerRole): string =>
    t(`app.public_talks.speakers.roles.${role}`);

const outlineLabel = (speaker: SpeakerDetail): string => {
    const numbers = props.outlines
        .filter((outline) => speaker.outline_ids.includes(outline.id))
        .map((outline) => outline.number);

    return numbers.length > 0 ? `nº ${numbers.join(', ')}` : '—';
};
</script>

<template>
    <Head :title="props.congregation.name" />

    <div class="flex flex-col space-y-6 p-4 sm:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-semibold">
                        {{ props.congregation.name }}
                    </h1>
                    <Badge
                        v-if="props.congregation.is_home"
                        variant="secondary"
                    >
                        {{ t('app.public_talks.congregations.home_badge') }}
                    </Badge>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{
                        [
                            props.congregation.city,
                            props.congregation.circuit,
                        ]
                            .filter(Boolean)
                            .join(' · ') || '—'
                    }}
                </p>
                <p
                    v-if="props.congregation.meeting_weekday !== null"
                    class="text-sm text-muted-foreground"
                >
                    {{
                        t(
                            `app.public_talks.weekdays.${props.congregation.meeting_weekday}`,
                        )
                    }}
                    <template v-if="props.congregation.meeting_time">
                        · {{ props.congregation.meeting_time }}
                    </template>
                </p>
            </div>

            <Button
                v-if="props.canManage"
                variant="outline"
                data-test="edit-congregation"
                @click="openEdit"
            >
                <Pencil class="size-4" />
                {{ t('app.public_talks.congregations.edit') }}
            </Button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-lg border p-4">
                <p class="text-sm font-medium">
                    {{ t('app.public_talks.congregations.contact_title') }}
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ props.congregation.contact_name || '—' }}
                </p>
                <p class="text-sm text-muted-foreground">
                    {{ props.congregation.contact_phone || '—' }}
                </p>
            </div>
            <div class="rounded-lg border p-4">
                <p class="text-sm font-medium">
                    {{ t('app.public_talks.congregations.secretary_title') }}
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ props.congregation.secretary_name || '—' }}
                </p>
                <p class="text-sm text-muted-foreground">
                    {{ props.congregation.secretary_phone || '—' }}
                </p>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <Heading
                title="app.public_talks.speakers.title"
                description="app.public_talks.speakers.description"
            />

            <Button
                v-if="props.canManage"
                data-test="add-speaker"
                @click="openSpeaker(null)"
            >
                <Plus class="size-4" />
                {{ t('app.public_talks.speakers.add') }}
            </Button>
        </div>

        <div
            v-if="props.speakers.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            {{ t('app.public_talks.speakers.empty') }}
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="speaker in props.speakers"
                :key="speaker.id"
                data-test="speaker-row"
                class="flex items-start justify-between gap-4 rounded-lg border p-4"
            >
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="font-medium">{{ speaker.name }}</span>
                        <Badge variant="outline">
                            {{ roleLabel(speaker.role) }}
                        </Badge>
                        <Badge v-if="!speaker.is_active" variant="secondary">
                            {{ t('app.public_talks.speakers.inactive') }}
                        </Badge>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ speaker.phone || '—' }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ t('app.public_talks.speakers.outlines_label') }}:
                        {{ outlineLabel(speaker) }}
                    </p>
                </div>

                <div v-if="props.canManage" class="flex shrink-0 gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        data-test="edit-speaker"
                        @click="openSpeaker(speaker)"
                    >
                        <Pencil class="size-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        data-test="delete-speaker"
                        @click="removeSpeaker(speaker)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit congregation -->
    <Dialog v-model:open="editOpen">
        <DialogContent class="max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>
                    {{ t('app.public_talks.congregations.edit') }}
                </DialogTitle>
            </DialogHeader>

            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label>{{
                        t('app.public_talks.congregations.name_label')
                    }}</Label>
                    <Input v-model="congregationForm.name" />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label>{{
                            t('app.public_talks.congregations.city_label')
                        }}</Label>
                        <Input v-model="congregationForm.city" />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{
                            t('app.public_talks.congregations.circuit_label')
                        }}</Label>
                        <Input v-model="congregationForm.circuit" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label>{{
                        t('app.public_talks.congregations.address_label')
                    }}</Label>
                    <Input v-model="congregationForm.address" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label>{{
                            t('app.public_talks.congregations.contact_name_label')
                        }}</Label>
                        <Input v-model="congregationForm.contact_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{
                            t('app.public_talks.congregations.contact_phone_label')
                        }}</Label>
                        <Input v-model="congregationForm.contact_phone" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label>{{
                            t('app.public_talks.congregations.secretary_name_label')
                        }}</Label>
                        <Input v-model="congregationForm.secretary_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{
                            t('app.public_talks.congregations.secretary_phone_label')
                        }}</Label>
                        <Input v-model="congregationForm.secretary_phone" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label>{{
                            t('app.public_talks.setup.weekday_label')
                        }}</Label>
                        <Select
                            :model-value="
                                congregationForm.meeting_weekday === null
                                    ? undefined
                                    : String(congregationForm.meeting_weekday)
                            "
                            @update:model-value="
                                (value) =>
                                    (congregationForm.meeting_weekday =
                                        value === undefined || value === null
                                            ? null
                                            : Number(value))
                            "
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="weekday in 7"
                                    :key="weekday"
                                    :value="String(weekday - 1)"
                                >
                                    {{
                                        t(
                                            `app.public_talks.weekdays.${weekday - 1}`,
                                        )
                                    }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label>{{
                            t('app.public_talks.setup.time_label')
                        }}</Label>
                        <Input
                            v-model="congregationForm.meeting_time"
                            type="time"
                        />
                        <InputError :message="errors.meeting_time" />
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <Button variant="outline" @click="editOpen = false">
                    {{ t('app.common.actions.cancel') }}
                </Button>
                <Button
                    :disabled="processing"
                    data-test="save-congregation"
                    @click="submitCongregation"
                >
                    {{ t('app.common.actions.save') }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>

    <!-- Speaker create/edit -->
    <Dialog v-model:open="speakerOpen">
        <DialogContent class="max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>
                    {{
                        editingSpeaker
                            ? t('app.public_talks.speakers.edit_title')
                            : t('app.public_talks.speakers.add')
                    }}
                </DialogTitle>
            </DialogHeader>

            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="speaker-name">{{
                        t('app.public_talks.speakers.name_label')
                    }}</Label>
                    <Input id="speaker-name" v-model="speakerForm.name" />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label>{{
                            t('app.public_talks.speakers.role_label')
                        }}</Label>
                        <Select v-model="speakerForm.role">
                            <SelectTrigger data-test="speaker-role">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="role in roles"
                                    :key="role"
                                    :value="role"
                                >
                                    {{ roleLabel(role) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.role" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="speaker-phone">{{
                            t('app.public_talks.speakers.phone_label')
                        }}</Label>
                        <Input
                            id="speaker-phone"
                            v-model="speakerForm.phone"
                        />
                        <InputError :message="errors.phone" />
                    </div>
                </div>

                <div class="flex items-center justify-between rounded-lg border p-3">
                    <Label for="speaker-active">{{
                        t('app.public_talks.speakers.active_label')
                    }}</Label>
                    <Switch
                        id="speaker-active"
                        v-model="speakerForm.is_active"
                    />
                </div>

                <div class="grid gap-2">
                    <Label>{{
                        t('app.public_talks.speakers.outlines_label')
                    }}</Label>
                    <div
                        class="max-h-48 space-y-1 overflow-y-auto rounded-lg border p-2"
                    >
                        <label
                            v-for="outline in props.outlines"
                            :key="outline.id"
                            class="flex cursor-pointer items-center gap-2 rounded p-1.5 text-sm hover:bg-accent"
                        >
                            <Checkbox
                                :model-value="
                                    speakerForm.outline_ids.includes(
                                        outline.id,
                                    )
                                "
                                @update:model-value="
                                    () => toggleOutline(outline.id)
                                "
                            />
                            <span class="text-muted-foreground">
                                {{ outline.number }}.
                            </span>
                            <span class="truncate">{{ outline.title }}</span>
                        </label>
                    </div>
                    <InputError :message="errors.outline_ids" />
                </div>

                <div class="grid gap-2">
                    <Label for="speaker-notes">{{
                        t('app.public_talks.speakers.notes_label')
                    }}</Label>
                    <Input id="speaker-notes" v-model="speakerForm.notes" />
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <Button variant="outline" @click="speakerOpen = false">
                    {{ t('app.common.actions.cancel') }}
                </Button>
                <Button
                    :disabled="processing || !speakerForm.name"
                    data-test="save-speaker"
                    @click="submitSpeaker"
                >
                    {{ t('app.common.actions.save') }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
