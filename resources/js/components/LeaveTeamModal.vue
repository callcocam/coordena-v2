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
import { leave as leaveTeamAction } from '@/routes/teams';
import type { Team } from '@/types';

type Props = {
    team: Team | null;
    open: boolean;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const { t } = useT();

const processing = ref(false);

const leaveTeam = () => {
    if (!props.team) {
        return;
    }

    router.visit(leaveTeamAction(props.team.slug), {
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
                <DialogTitle>{{ t('app.teams.leave.title') }}</DialogTitle>
                <DialogDescription>
                    {{ t('app.teams.leave.confirm_prefix') }}
                    <strong>{{ props.team?.name }}</strong
                    >?
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">
                        {{ t('app.common.actions.cancel') }}
                    </Button>
                </DialogClose>

                <Button
                    data-test="leave-team-confirm"
                    variant="destructive"
                    :disabled="processing"
                    @click="leaveTeam"
                >
                    {{ t('app.teams.leave.button') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
