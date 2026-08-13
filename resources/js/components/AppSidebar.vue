<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, CalendarDays, FolderGit2, LayoutGrid, Library, UserCog } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import TeamSwitcher from '@/components/TeamSwitcher.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { usePermissions } from '@/composables/usePermissions';
import { dashboard } from '@/routes';
import { index as congregationsIndex } from '@/routes/acervo/congregations';
import { schedule } from '@/routes/public-talks';
import { index as coordinatorsIndex } from '@/routes/public-talks/coordinators';
import type { NavItem } from '@/types';

const page = usePage();
const { can } = usePermissions();

const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'app.nav.sidebar.dashboard',
            href: dashboardUrl.value,
            icon: LayoutGrid,
        },
    ];

    const teamSlug = page.props.currentTeam?.slug;

    if (teamSlug && can('public-talks:view')) {
        items.push({
            title: 'app.public_talks.nav.schedule',
            href: schedule(teamSlug).url,
            icon: CalendarDays,
        });
    }

    if (teamSlug && can('congregations:view')) {
        items.push({
            title: 'app.public_talks.nav.acervo',
            href: congregationsIndex(teamSlug).url,
            icon: Library,
        });
    }

    if (teamSlug && can('public-talks:manage')) {
        items.push({
            title: 'app.public_talks.nav.coordinators',
            href: coordinatorsIndex(teamSlug).url,
            icon: UserCog,
        });
    }

    return items;
});

const footerNavItems: NavItem[] = [
    {
        title: 'app.nav.links.repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'app.nav.links.documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboardUrl">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <SidebarMenu>
                <SidebarMenuItem>
                    <TeamSwitcher />
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
