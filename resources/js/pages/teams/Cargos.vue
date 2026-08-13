<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import CargoFormModal from '@/components/teams/CargoFormModal.vue';
import DeleteCargoModal from '@/components/teams/DeleteCargoModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit, index } from '@/routes/teams';
import { index as cargosIndex } from '@/routes/teams/cargos';
import type {
    CargoDetail,
    PermissionGroup,
    Team,
    TeamPermissions,
} from '@/types';

type GlobalCargo = {
    key: string;
    name: string;
    isSuper: boolean;
    permissions: string[];
};

type Props = {
    team: Team;
    globalCargos: GlobalCargo[];
    customCargos: CargoDetail[];
    permissionGroups: PermissionGroup[];
    permissions: TeamPermissions;
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: { team: Team }) => [
        [
            AppLayout,
            {
                breadcrumbs: [
                    {
                        title: 'app.teams.index.breadcrumb',
                        href: index(),
                    },
                    {
                        title: props.team.name,
                        href: edit(props.team.slug),
                    },
                    {
                        title: 'app.teams.cargos.breadcrumb',
                        href: cargosIndex(props.team.slug),
                    },
                ],
            },
        ],
        SettingsLayout,
    ],
});

const { t } = useT();

const permissionLabels = computed<Record<string, string>>(() => {
    const labels: Record<string, string> = {};

    for (const group of props.permissionGroups) {
        for (const permission of group.permissions) {
            labels[permission.name] = permission.label;
        }
    }

    return labels;
});

const permissionLabel = (name: string): string =>
    permissionLabels.value[name] ?? name;

const formOpen = ref(false);
const deleteOpen = ref(false);
const activeCargo = ref<CargoDetail | null>(null);

const openCreate = () => {
    activeCargo.value = null;
    formOpen.value = true;
};

const openEdit = (cargo: CargoDetail) => {
    activeCargo.value = cargo;
    formOpen.value = true;
};

const openDelete = (cargo: CargoDetail) => {
    activeCargo.value = cargo;
    deleteOpen.value = true;
};
</script>

<template>
    <Head :title="t('app.teams.cargos.title')" />

    <div class="flex flex-col space-y-10">
        <!-- Cargos padrão (globais, somente leitura) -->
        <div class="space-y-6">
            <Heading
                variant="small"
                title="app.teams.cargos.default_title"
                description="app.teams.cargos.default_description"
            />

            <div class="space-y-3">
                <div
                    v-for="cargo in props.globalCargos"
                    :key="cargo.key"
                    data-test="global-cargo-row"
                    class="rounded-lg border p-4"
                >
                    <div class="font-medium">{{ cargo.name }}</div>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <Badge v-if="cargo.isSuper" variant="default">
                            {{ t('app.teams.cargos.super_badge') }}
                        </Badge>
                        <template v-else-if="cargo.permissions.length > 0">
                            <Badge
                                v-for="permission in cargo.permissions"
                                :key="permission"
                                variant="secondary"
                            >
                                {{ permissionLabel(permission) }}
                            </Badge>
                        </template>
                        <span v-else class="text-sm text-muted-foreground">
                            {{ t('app.teams.cargos.no_permissions') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cargos personalizados -->
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <Heading
                    variant="small"
                    title="app.teams.cargos.custom_title"
                    description="app.teams.cargos.custom_description"
                />

                <Button
                    v-if="props.permissions.canManageRoles"
                    data-test="new-cargo-button"
                    @click="openCreate"
                >
                    <Plus /> {{ t('app.teams.cargos.new') }}
                </Button>
            </div>

            <div
                v-if="props.customCargos.length === 0"
                class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                {{ t('app.teams.cargos.custom_empty') }}
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="cargo in props.customCargos"
                    :key="cargo.id"
                    data-test="custom-cargo-row"
                    class="flex items-start justify-between gap-4 rounded-lg border p-4"
                >
                    <div class="min-w-0">
                        <div class="font-medium">{{ cargo.name }}</div>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <template v-if="cargo.permissions.length > 0">
                                <Badge
                                    v-for="permission in cargo.permissions"
                                    :key="permission"
                                    variant="secondary"
                                >
                                    {{ permissionLabel(permission) }}
                                </Badge>
                            </template>
                            <span v-else class="text-sm text-muted-foreground">
                                {{ t('app.teams.cargos.no_permissions') }}
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="props.permissions.canManageRoles"
                        class="flex shrink-0 items-center gap-2"
                    >
                        <Button
                            data-test="edit-cargo-button"
                            variant="ghost"
                            size="sm"
                            @click="openEdit(cargo)"
                        >
                            <Pencil class="h-4 w-4" />
                        </Button>
                        <Button
                            data-test="delete-cargo-button"
                            variant="ghost"
                            size="sm"
                            @click="openDelete(cargo)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <CargoFormModal
        v-if="props.permissions.canManageRoles"
        :team="props.team"
        :permission-groups="props.permissionGroups"
        :cargo="activeCargo"
        :open="formOpen"
        @update:open="formOpen = $event"
    />

    <DeleteCargoModal
        v-if="props.permissions.canManageRoles"
        :team="props.team"
        :cargo="activeCargo"
        :open="deleteOpen"
        @update:open="deleteOpen = $event"
    />
</template>
