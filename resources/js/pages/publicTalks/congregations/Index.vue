<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PageContainer from '@/components/PageContainer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { index as congregationsIndex, show, store } from '@/routes/acervo/congregations';
import type { CongregationSummary } from '@/types';

type Props = {
    congregations: CongregationSummary[];
    filters: { q: string };
    canManage: boolean;
};

const props = defineProps<Props>();

defineOptions({
    layout: AppLayout,
});

const { t } = useT();
const page = usePage();

const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const search = ref(props.filters.q);

const applySearch = () => {
    router.get(
        congregationsIndex(teamSlug.value).url,
        { q: search.value || undefined },
        { preserveState: true, preserveScroll: true },
    );
};

/* Create dialog */
const createOpen = ref(false);
const name = ref('');
const city = ref('');
const circuit = ref('');
const errors = ref<Record<string, string>>({});
const processing = ref(false);

const openCreate = () => {
    name.value = '';
    city.value = '';
    circuit.value = '';
    errors.value = {};
    createOpen.value = true;
};

const submitCreate = () => {
    router.post(
        store(teamSlug.value).url,
        {
            name: name.value,
            city: city.value || null,
            circuit: circuit.value || null,
        },
        {
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
            onError: (formErrors) => (errors.value = formErrors),
            onSuccess: () => (createOpen.value = false),
        },
    );
};
</script>

<template>
    <Head :title="t('app.public_talks.congregations.title')" />

    <PageContainer
        :title="t('app.public_talks.congregations.title')"
        :description="t('app.public_talks.congregations.description')"
        :back-href="dashboard(teamSlug)"
    >
        <template #actions>
            <Button
                v-if="props.canManage"
                data-test="add-congregation"
                @click="openCreate"
            >
                <Plus class="size-4" />
                {{ t('app.public_talks.congregations.add') }}
            </Button>
        </template>

        <div class="relative max-w-sm">
            <Search
                class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="search"
                class="pl-9"
                data-test="congregation-search"
                :placeholder="
                    t('app.public_talks.congregations.search_placeholder')
                "
                @keyup.enter="applySearch"
                @blur="applySearch"
            />
        </div>

        <div
            v-if="props.congregations.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            {{ t('app.public_talks.congregations.empty') }}
        </div>

        <div v-else class="space-y-3">
            <Link
                v-for="congregation in props.congregations"
                :key="congregation.id"
                :href="show([teamSlug, congregation.id]).url"
                data-test="congregation-row"
                class="block rounded-lg border p-4 transition-colors hover:bg-accent"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-medium">
                                {{ congregation.name }}
                            </span>
                            <Badge
                                v-if="congregation.is_home"
                                variant="secondary"
                            >
                                {{
                                    t(
                                        'app.public_talks.congregations.home_badge',
                                    )
                                }}
                            </Badge>
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{
                                [congregation.city, congregation.circuit]
                                    .filter(Boolean)
                                    .join(' · ') || '—'
                            }}
                        </p>
                    </div>

                    <span class="text-sm text-muted-foreground">
                        {{
                            t(
                                'app.public_talks.congregations.speakers_count',
                                { count: congregation.speakers_count },
                            )
                        }}
                    </span>
                </div>
            </Link>
        </div>
    </PageContainer>

    <Dialog v-model:open="createOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    {{ t('app.public_talks.congregations.add') }}
                </DialogTitle>
                <DialogDescription>
                    {{ t('app.public_talks.congregations.description') }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="congregation-name">{{
                        t('app.public_talks.congregations.name_label')
                    }}</Label>
                    <Input id="congregation-name" v-model="name" />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="congregation-city">{{
                            t('app.public_talks.congregations.city_label')
                        }}</Label>
                        <Input id="congregation-city" v-model="city" />
                        <InputError :message="errors.city" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="congregation-circuit">{{
                            t('app.public_talks.congregations.circuit_label')
                        }}</Label>
                        <Input id="congregation-circuit" v-model="circuit" />
                        <InputError :message="errors.circuit" />
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <Button variant="outline" @click="createOpen = false">
                    {{ t('app.common.actions.cancel') }}
                </Button>
                <Button
                    :disabled="processing || !name"
                    data-test="save-congregation"
                    @click="submitCreate"
                >
                    {{ t('app.common.actions.save') }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
