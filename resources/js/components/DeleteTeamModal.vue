<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';
import { destroy } from '@/routes/teams';
import type { Team } from '@/types';

type Props = {
    team: Team;
    open: boolean;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const { t } = useT();

const confirmationName = ref('');
const formKey = ref(0);

const canDeleteTeam = computed(() => {
    return confirmationName.value === props.team.name;
});

const handleOpenChange = (nextOpen: boolean) => {
    emit('update:open', nextOpen);

    if (!nextOpen) {
        confirmationName.value = '';
        formKey.value++;
    }
};
</script>

<template>
    <Dialog :open="props.open" @update:open="handleOpenChange">
        <DialogContent>
            <Form
                :key="formKey"
                v-bind="destroy.form(props.team.slug)"
                class="space-y-6"
                v-slot="{ errors, processing }"
                @success="handleOpenChange(false)"
            >
                <DialogHeader>
                    <DialogTitle>{{ t('app.teams.delete.confirm_title') }}</DialogTitle>
                    <DialogDescription>
                        {{ t('app.teams.delete.confirm_description') }}
                        <strong>"{{ props.team.name }}"</strong>.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="grid gap-2">
                        <Label for="confirmation-name">
                            {{ t('app.teams.delete.confirm_type_prefix') }}
                            <strong>"{{ props.team.name }}"</strong>
                            {{ t('app.teams.delete.confirm_type_suffix') }}
                        </Label>
                        <Input
                            id="confirmation-name"
                            name="name"
                            data-test="delete-team-name"
                            v-model="confirmationName"
                            :placeholder="t('app.teams.delete.name_placeholder')"
                            autocomplete="off"
                        />
                        <InputError :message="errors.name" />
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">
                            {{ t('app.common.actions.cancel') }}
                        </Button>
                    </DialogClose>

                    <Button
                        data-test="delete-team-confirm"
                        variant="destructive"
                        type="submit"
                        :disabled="!canDeleteTeam || processing"
                    >
                        {{ t('app.teams.delete.button') }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
