<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
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
import AppLayout from '@/layouts/AppLayout.vue';
import {
    congregation as setupCongregation,
    coordinator as setupCoordinator,
} from '@/routes/public-talks/setup';

type CongregationOption = {
    id: string;
    name: string;
    city: string | null;
};

type Props = {
    step: 'congregation' | 'coordinator';
    congregations: CongregationOption[];
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

/* Step 1 — home congregation */
const congregationId = ref<string | null>(null);
const name = ref('');
const city = ref('');
const meetingWeekday = ref<string | null>(null);
const meetingTime = ref('');

const weekdays = ['0', '1', '2', '3', '4', '5', '6'];

const submitCongregation = () => {
    router.post(
        setupCongregation(teamSlug.value).url,
        congregationId.value
            ? { congregation_id: congregationId.value }
            : {
                  congregation_id: null,
                  name: name.value,
                  city: city.value || null,
                  meeting_weekday:
                      meetingWeekday.value === null
                          ? null
                          : Number(meetingWeekday.value),
                  meeting_time: meetingTime.value || null,
              },
        {
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
            onError: (formErrors) => (errors.value = formErrors),
        },
    );
};

/* Step 2 — responsible coordinator */
const coordinatorName = ref('');
const coordinatorPhone = ref('');
const helpers = ref<{ name: string; phone: string }[]>([]);

const addHelper = () => {
    helpers.value.push({ name: '', phone: '' });
};

const removeHelper = (index: number) => {
    helpers.value.splice(index, 1);
};

const submitCoordinator = () => {
    router.post(
        setupCoordinator(teamSlug.value).url,
        {
            name: coordinatorName.value,
            phone: coordinatorPhone.value || null,
            helpers: helpers.value.map((helper) => ({
                name: helper.name,
                phone: helper.phone || null,
            })),
        },
        {
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
            onError: (formErrors) => (errors.value = formErrors),
        },
    );
};
</script>

<template>
    <Head :title="t('app.public_talks.setup.title')" />

    <div class="mx-auto w-full max-w-xl space-y-6 p-4 sm:p-6">
        <template v-if="props.step === 'congregation'">
            <Heading
                title="app.public_talks.setup.congregation_title"
                description="app.public_talks.setup.congregation_description"
            />

            <div class="grid gap-2">
                <Label>{{
                    t('app.public_talks.setup.existing_label')
                }}</Label>
                <Select v-model="congregationId">
                    <SelectTrigger data-test="existing-congregation">
                        <SelectValue
                            :placeholder="
                                t(
                                    'app.public_talks.setup.existing_placeholder',
                                )
                            "
                        />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in props.congregations"
                            :key="option.id"
                            :value="option.id"
                        >
                            {{ option.name }}
                            <span
                                v-if="option.city"
                                class="text-muted-foreground"
                            >
                                — {{ option.city }}
                            </span>
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.congregation_id" />
            </div>

            <div v-if="!congregationId" class="grid gap-4">
                <p class="text-sm font-medium">
                    {{ t('app.public_talks.setup.or_create') }}
                </p>

                <div class="grid gap-2">
                    <Label for="setup-name">{{
                        t('app.public_talks.setup.name_label')
                    }}</Label>
                    <Input id="setup-name" v-model="name" />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="setup-city">{{
                        t('app.public_talks.setup.city_label')
                    }}</Label>
                    <Input id="setup-city" v-model="city" />
                    <InputError :message="errors.city" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label>{{
                            t('app.public_talks.setup.weekday_label')
                        }}</Label>
                        <Select v-model="meetingWeekday">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="weekday in weekdays"
                                    :key="weekday"
                                    :value="weekday"
                                >
                                    {{
                                        t(
                                            `app.public_talks.weekdays.${weekday}`,
                                        )
                                    }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.meeting_weekday" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="setup-time">{{
                            t('app.public_talks.setup.time_label')
                        }}</Label>
                        <Input
                            id="setup-time"
                            v-model="meetingTime"
                            type="time"
                        />
                        <InputError :message="errors.meeting_time" />
                    </div>
                </div>
            </div>

            <Button
                class="w-full"
                :disabled="processing || (!congregationId && !name)"
                data-test="setup-congregation-submit"
                @click="submitCongregation"
            >
                {{ t('app.public_talks.setup.continue') }}
            </Button>
        </template>

        <template v-else>
            <Heading
                title="app.public_talks.setup.coordinator_title"
                description="app.public_talks.setup.coordinator_description"
            />

            <div class="grid gap-2">
                <Label for="coordinator-name">{{
                    t('app.public_talks.setup.coordinator_name_label')
                }}</Label>
                <Input id="coordinator-name" v-model="coordinatorName" />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="coordinator-phone">{{
                    t('app.public_talks.setup.coordinator_phone_label')
                }}</Label>
                <Input id="coordinator-phone" v-model="coordinatorPhone" />
                <InputError :message="errors.phone" />
            </div>

            <div class="space-y-3">
                <div
                    v-for="(helper, index) in helpers"
                    :key="index"
                    class="flex items-end gap-2"
                >
                    <div class="grid flex-1 gap-2">
                        <Label>{{
                            t('app.public_talks.coordinators.name_label')
                        }}</Label>
                        <Input v-model="helper.name" />
                        <InputError
                            :message="errors[`helpers.${index}.name`]"
                        />
                    </div>
                    <div class="grid flex-1 gap-2">
                        <Label>{{
                            t('app.public_talks.coordinators.phone_label')
                        }}</Label>
                        <Input v-model="helper.phone" />
                    </div>
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="removeHelper(index)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>

                <Button variant="outline" size="sm" @click="addHelper">
                    <Plus class="size-4" />
                    {{ t('app.public_talks.coordinators.add') }}
                </Button>
            </div>

            <Button
                class="w-full"
                :disabled="processing || !coordinatorName"
                data-test="setup-coordinator-submit"
                @click="submitCoordinator"
            >
                {{ t('app.public_talks.setup.finish') }}
            </Button>
        </template>
    </div>
</template>
