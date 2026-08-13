<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useT } from '@/composables/useT';
import { destroy as destroyCargo } from '@/routes/teams/cargos';
import type { CargoDetail, Team } from '@/types';

type Props = {
    team: Team;
    cargo: CargoDetail | null;
    open: boolean;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const { t } = useT();

const processing = ref(false);

const deleteCargo = () => {
    if (!props.cargo?.id) {
        return;
    }

    router.delete(destroyCargo([props.team.slug, props.cargo.id]).url, {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
        onSuccess: () => emit('update:open', false),
    });
};
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{
                    t('app.teams.cargos.delete_title')
                }}</DialogTitle>
                <DialogDescription>
                    {{ t('app.teams.cargos.delete_confirm_prefix') }}
                    <strong>{{ props.cargo?.name }}</strong
                    >{{ t('app.teams.cargos.delete_confirm_suffix') }}
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">
                        {{ t('app.common.actions.cancel') }}
                    </Button>
                </DialogClose>

                <Button
                    data-test="delete-cargo-confirm"
                    variant="destructive"
                    :disabled="processing"
                    @click="deleteCargo"
                >
                    {{ t('app.teams.cargos.delete_button') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
