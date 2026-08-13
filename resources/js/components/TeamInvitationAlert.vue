<script setup lang="ts">
import { Info } from '@lucide/vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useT } from '@/composables/useT';
import type { TeamInvitationContext } from '@/types';

type Props = {
    invitation: TeamInvitationContext;
    action: string;
};

const props = defineProps<Props>();

const { t } = useT();
</script>

<template>
    <div data-test="team-invitation-alert">
        <Alert
            class="border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-900/50 dark:bg-blue-950/50 dark:text-blue-100 [&>svg]:text-blue-600 dark:[&>svg]:text-blue-400"
        >
            <Info class="size-4" />
            <AlertDescription class="text-blue-900 dark:text-blue-100">
                {{
                    t('app.auth.invitation_alert.message', {
                        action: props.action,
                        team: props.invitation.teamName,
                    })
                }}
            </AlertDescription>
        </Alert>
    </div>
</template>
