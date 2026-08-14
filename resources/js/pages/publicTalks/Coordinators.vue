<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import {
    destroy as destroyCoordinator,
    store as storeCoordinator,
    update as updateCoordinator,
} from '@/routes/public-talks/coordinators';
import type { CoordinatorItem, CoordinatorRole } from '@/types';

type Props = {
    coordinators: CoordinatorItem[];
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

const roles: CoordinatorRole[] = ['responsible', 'helper'];

const dialogOpen = ref(false);
const editing = ref<CoordinatorItem | null>(null);
const form = ref({
    name: '',
    phone: '',
    role: 'helper' as CoordinatorRole,
    is_active: true,
});

const openDialog = (coordinator: CoordinatorItem | null) => {
    editing.value = coordinator;
    form.value = coordinator
        ? {
              name: coordinator.name,
              phone: coordinator.phone ?? '',
              role: coordinator.role,
              is_active: coordinator.is_active,
          }
        : { name: '', phone: '', role: 'helper', is_active: true };
    errors.value = {};
    dialogOpen.value = true;
};

const submit = () => {
    const payload = {
        name: form.value.name,
        phone: form.value.phone || null,
        role: form.value.role,
        is_active: form.value.is_active,
    };

    const options = {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
        onError: (formErrors: Record<string, string>) =>
            (errors.value = formErrors),
        onSuccess: () => (dialogOpen.value = false),
    };

    if (editing.value) {
        router.put(
            updateCoordinator([teamSlug.value, editing.value.id]).url,
            payload,
            options,
        );

        return;
    }

    router.post(storeCoordinator(teamSlug.value).url, payload, options);
};

const remove = (coordinator: CoordinatorItem) => {
    router.delete(destroyCoordinator([teamSlug.value, coordinator.id]).url, {
        preserveScroll: true,
    });
};

const roleLabel = (role: CoordinatorRole): string =>
    t(`app.public_talks.coordinators.roles.${role}`);
</script>

<template>
    <Head :title="t('app.public_talks.coordinators.title')" />

    <div class="flex flex-col space-y-6 p-4 sm:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
        >
            <Heading
                title="app.public_talks.coordinators.title"
                description="app.public_talks.coordinators.description"
            />

            <Button
                v-if="props.canManage"
                data-test="add-coordinator"
                @click="openDialog(null)"
            >
                <Plus class="size-4" />
                {{ t('app.public_talks.coordinators.add') }}
            </Button>
        </div>

        <div
            v-if="props.coordinators.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            {{ t('app.public_talks.coordinators.empty') }}
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="coordinator in props.coordinators"
                :key="coordinator.id"
                data-test="coordinator-row"
                class="flex items-start justify-between gap-4 rounded-lg border p-4"
            >
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="font-medium">{{ coordinator.name }}</span>
                        <Badge
                            :variant="
                                coordinator.role === 'responsible'
                                    ? 'default'
                                    : 'outline'
                            "
                        >
                            {{ roleLabel(coordinator.role) }}
                        </Badge>
                        <Badge
                            v-if="!coordinator.is_active"
                            variant="secondary"
                        >
                            {{ t('app.public_talks.coordinators.inactive') }}
                        </Badge>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ coordinator.phone || '—' }}
                    </p>
                </div>

                <div v-if="props.canManage" class="flex shrink-0 gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        data-test="edit-coordinator"
                        @click="openDialog(coordinator)"
                    >
                        <Pencil class="size-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        data-test="delete-coordinator"
                        @click="remove(coordinator)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </div>
        </div>
    </div>

    <Dialog v-model:open="dialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    {{
                        editing
                            ? t('app.public_talks.coordinators.edit_title')
                            : t('app.public_talks.coordinators.add')
                    }}
                </DialogTitle>
            </DialogHeader>

            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="coordinator-name">{{
                        t('app.public_talks.coordinators.name_label')
                    }}</Label>
                    <Input id="coordinator-name" v-model="form.name" />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="coordinator-phone">{{
                            t('app.public_talks.coordinators.phone_label')
                        }}</Label>
                        <Input id="coordinator-phone" v-model="form.phone" />
                        <InputError :message="errors.phone" />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{
                            t('app.public_talks.coordinators.role_label')
                        }}</Label>
                        <Select v-model="form.role">
                            <SelectTrigger
                                class="w-full"
                                data-test="coordinator-role"
                            >
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
                </div>

                <div
                    class="flex items-center justify-between rounded-lg border p-3"
                >
                    <Label for="coordinator-active">{{
                        t('app.public_talks.coordinators.active_label')
                    }}</Label>
                    <Switch
                        id="coordinator-active"
                        v-model="form.is_active"
                    />
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <Button variant="outline" @click="dialogOpen = false">
                    {{ t('app.common.actions.cancel') }}
                </Button>
                <Button
                    :disabled="processing || !form.name"
                    data-test="save-coordinator"
                    @click="submit"
                >
                    {{ t('app.common.actions.save') }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
