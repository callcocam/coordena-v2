<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Eye, LogOut, Pencil, Plus } from '@lucide/vue';
import { ref } from 'vue';
import CreateTeamModal from '@/components/CreateTeamModal.vue';
import Heading from '@/components/Heading.vue';
import LeaveTeamModal from '@/components/LeaveTeamModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit, index } from '@/routes/teams';
import type { Team } from '@/types';

type Props = {
    teams: Team[];
};

defineProps<Props>();

const { t } = useT();

const leaveTeamDialogOpen = ref(false);
const teamLeaving = ref<Team | null>(null);

const canLeaveTeam = (team: Team) => !team.isPersonal && !team.isOwner;

const teamCargoLabel = (team: Team) =>
    (team.cargos ?? []).map((cargo) => cargo.name).join(', ');

const openLeaveTeamDialog = (team: Team) => {
    teamLeaving.value = team;
    leaveTeamDialogOpen.value = true;
};

defineOptions({
    layout: [
        [
            AppLayout,
            {
                breadcrumbs: [
                    {
                        title: 'app.teams.index.breadcrumb',
                        href: index(),
                    },
                ],
            },
        ],
        SettingsLayout,
    ],
});
</script>

<template>
    <Head :title="t('app.teams.index.meta_title')" />

    <h1 class="sr-only">{{ t('app.teams.index.title') }}</h1>

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="app.teams.index.title"
                description="app.teams.index.description"
            />

            <CreateTeamModal>
                <Button data-test="teams-new-team-button">
                    <Plus /> {{ t('app.teams.index.new_team') }}
                </Button>
            </CreateTeamModal>
        </div>

        <div class="space-y-3">
            <div
                v-for="team in teams"
                :key="team.id"
                data-test="team-row"
                class="flex items-center justify-between gap-4 rounded-lg border p-4"
            >
                <div class="flex items-center gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-medium">{{ team.name }}</span>
                            <Badge v-if="team.isPersonal" variant="secondary">
                                {{ t('app.teams.index.personal') }}
                            </Badge>
                        </div>
                        <span class="text-sm text-muted-foreground">
                            {{ teamCargoLabel(team) }}
                        </span>
                    </div>
                </div>

                <TooltipProvider>
                    <div class="flex items-center gap-2">
                        <Tooltip v-if="canLeaveTeam(team)">
                            <TooltipTrigger as-child>
                                <Button
                                    data-test="team-leave-button"
                                    variant="ghost"
                                    size="sm"
                                    @click="openLeaveTeamDialog(team)"
                                >
                                    <LogOut class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>{{ t('app.teams.leave.tooltip') }}</p>
                            </TooltipContent>
                        </Tooltip>

                        <Tooltip v-if="!team.isOwner">
                            <TooltipTrigger as-child>
                                <Button
                                    data-test="team-view-button"
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                >
                                    <Link :href="edit(team.slug)">
                                        <Eye class="h-4 w-4" />
                                    </Link>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>{{ t('app.teams.index.view_team') }}</p>
                            </TooltipContent>
                        </Tooltip>

                        <Tooltip v-else>
                            <TooltipTrigger as-child>
                                <Button
                                    data-test="team-edit-button"
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                >
                                    <Link :href="edit(team.slug)">
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>{{ t('app.teams.index.edit_team') }}</p>
                            </TooltipContent>
                        </Tooltip>
                    </div>
                </TooltipProvider>
            </div>

            <p
                v-if="teams.length === 0"
                class="py-8 text-center text-muted-foreground"
            >
                {{ t('app.teams.index.empty') }}
            </p>
        </div>
    </div>

    <LeaveTeamModal v-model:open="leaveTeamDialogOpen" :team="teamLeaving" />
</template>
