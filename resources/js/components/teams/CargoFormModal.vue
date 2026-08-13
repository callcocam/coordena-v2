<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';
import { store as storeCargo, update as updateCargo } from '@/routes/teams/cargos';
import type { CargoDetail, PermissionGroup, Team } from '@/types';

type Props = {
    team: Team;
    permissionGroups: PermissionGroup[];
    cargo: CargoDetail | null;
    open: boolean;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const { t } = useT();

const name = ref('');
const selectedPermissions = ref<string[]>([]);
const errors = ref<Record<string, string>>({});
const processing = ref(false);

const isEditing = () => Boolean(props.cargo?.id);

const seedFromCargo = () => {
    name.value = props.cargo?.name ?? '';
    selectedPermissions.value = [...(props.cargo?.permissions ?? [])];
    errors.value = {};
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            seedFromCargo();
        }
    },
);

const isChecked = (permission: string): boolean =>
    selectedPermissions.value.includes(permission);

const togglePermission = (permission: string, checked: boolean) => {
    if (checked) {
        if (!selectedPermissions.value.includes(permission)) {
            selectedPermissions.value = [
                ...selectedPermissions.value,
                permission,
            ];
        }

        return;
    }

    selectedPermissions.value = selectedPermissions.value.filter(
        (current) => current !== permission,
    );
};

const groupLabel = (group: string): string =>
    t(`app.teams.cargos.groups.${group}`);

const submit = () => {
    const target =
        isEditing() && props.cargo?.id
            ? updateCargo([props.team.slug, props.cargo.id])
            : storeCargo(props.team.slug);

    router[isEditing() ? 'put' : 'post'](target.url, {
        name: name.value,
        permissions: selectedPermissions.value,
    }, {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
        onError: (formErrors) => (errors.value = formErrors),
        onSuccess: () => emit('update:open', false),
    });
};
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{
                        isEditing()
                            ? t('app.teams.cargos.edit_title')
                            : t('app.teams.cargos.create_title')
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{ t('app.teams.cargos.description') }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="cargo-name">{{
                        t('app.teams.cargos.name_label')
                    }}</Label>
                    <Input
                        id="cargo-name"
                        v-model="name"
                        data-test="cargo-name-input"
                        :placeholder="t('app.teams.cargos.name_placeholder')"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-4">
                    <Label>{{ t('app.teams.cargos.permissions_label') }}</Label>

                    <div
                        v-for="group in props.permissionGroups"
                        :key="group.group"
                        class="grid gap-2"
                    >
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{ groupLabel(group.group) }}
                        </p>
                        <Label
                            v-for="permission in group.permissions"
                            :key="permission.name"
                            class="flex items-center gap-2 font-normal"
                        >
                            <Checkbox
                                :model-value="isChecked(permission.name)"
                                data-test="cargo-permission-option"
                                @update:model-value="
                                    togglePermission(
                                        permission.name,
                                        $event === true,
                                    )
                                "
                            />
                            {{ permission.label }}
                        </Label>
                    </div>
                    <InputError :message="errors.permissions" />
                </div>
            </div>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">
                        {{ t('app.common.actions.cancel') }}
                    </Button>
                </DialogClose>

                <Button
                    type="button"
                    data-test="cargo-save-button"
                    :disabled="processing"
                    @click="submit"
                >
                    {{ t('app.common.actions.save') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
