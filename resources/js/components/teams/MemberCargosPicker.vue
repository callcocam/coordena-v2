<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useT } from '@/composables/useT';
import { update as updateMember } from '@/routes/teams/members';
import type { RoleOption, Team, TeamMember } from '@/types';

type Props = {
    team: Team;
    member: TeamMember;
    availableRoles: RoleOption[];
};

const props = defineProps<Props>();

const { t } = useT();

const open = ref(false);
const processing = ref(false);
const selected = ref<string[]>([]);

const memberCargoLabel = () =>
    props.member.cargos.map((cargo) => cargo.name).join(', ') ||
    t('app.teams.members.no_cargo');

const syncFromMember = () => {
    selected.value = props.member.cargos.map((cargo) => cargo.key);
};

watch(open, (isOpen) => {
    if (isOpen) {
        syncFromMember();
    }
});

const isChecked = (key: string): boolean => selected.value.includes(key);

const toggle = (key: string, checked: boolean) => {
    if (checked) {
        if (!selected.value.includes(key)) {
            selected.value = [...selected.value, key];
        }

        return;
    }

    selected.value = selected.value.filter((current) => current !== key);
};

const apply = () => {
    router.visit(updateMember([props.team.slug, props.member.id]), {
        data: { cargos: selected.value },
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
        onSuccess: () => (open.value = false),
    });
};
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                data-test="member-cargos-trigger"
                variant="outline"
                size="sm"
            >
                {{ memberCargoLabel() }}
                <ChevronDown class="ml-2 h-4 w-4 opacity-50" />
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-64" align="end">
            <div class="space-y-4">
                <div class="space-y-1">
                    <p class="text-sm font-medium">
                        {{ t('app.teams.members.cargos_title') }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ t('app.teams.members.cargos_description') }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label
                        v-for="role in props.availableRoles"
                        :key="role.key"
                        class="flex items-center gap-2 font-normal"
                    >
                        <Checkbox
                            :model-value="isChecked(role.key)"
                            data-test="member-cargo-option"
                            @update:model-value="
                                toggle(role.key, $event === true)
                            "
                        />
                        {{ role.name }}
                    </Label>
                </div>

                <Button
                    class="w-full"
                    size="sm"
                    data-test="member-cargos-apply"
                    :disabled="processing || selected.length === 0"
                    @click="apply"
                >
                    {{ t('app.common.actions.apply') }}
                </Button>
            </div>
        </PopoverContent>
    </Popover>
</template>
