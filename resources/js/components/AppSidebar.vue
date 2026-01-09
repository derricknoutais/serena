<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard as appDashboard } from '@/routes';
import { dashboard as frontdeskDashboard } from '@/routes/frontdesk';
import { index as housekeepingIndex } from '@/routes/housekeeping';
import { index as activityIndex } from '@/routes/activity/index';
// import { index as activityIndex } from '@/routes/activity';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    LayoutGrid,
    ConciergeBell,
    CalendarDays,
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { computed, ref } from 'vue';

const baseMainNavItems: (NavItem & { requiredPermission?: string })[] = [
    {
        title: 'Dashboard',
        href: appDashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Night Audit',
        href: '/night-audit',
        icon: Activity,
        requiredPermission: 'night_audit_view',
    },
    {
        title: 'Frontdesk',
        href: frontdeskDashboard(),
        icon: ConciergeBell,
    },
];

const page = usePage();
const pageProps = computed(() => (page?.props ? page.props : page.value.props));
const permissions = computed<Record<string, boolean>>(
    () => pageProps.value?.auth?.can ?? {},
);
const canViewFrontdesk = computed(() => Boolean(permissions.value?.frontdesk_view ?? false));
const canViewHousekeeping = computed(() => Boolean(permissions.value?.housekeeping_view ?? false));
const currentUrl = computed(() => (page?.url ? page.url : page.value.url) || '');

const mainNavItems = computed(() => {
    return baseMainNavItems.filter((item) => {
        if (item.requiredPermission && !(permissions.value[item.requiredPermission] ?? false)) {
            return false;
        }

        if (item.title === 'Frontdesk') {
            return canViewFrontdesk.value;
        }

        return true;
    });
});

const receptionOpen = ref(currentUrl.value.startsWith('/frontdesk'));

const footerNavItems: NavItem[] = [
    {
        title: 'Journal d’Activités',
        href: activityIndex(),
        icon: Activity,
    }
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="canViewHousekeeping && !canViewFrontdesk ? housekeepingIndex() : appDashboard()">
                        <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <SidebarMenu v-if="canViewFrontdesk" class="px-2">
                <SidebarMenuItem>
                    <SidebarMenuButton
                        class="justify-between"
                        type="button"
                        @click.stop="receptionOpen = !receptionOpen"
                    >
                        <div class="flex items-center gap-2">
                            <ConciergeBell class="h-4 w-4" />
                            <span>Réception</span>
                        </div>
                        <span class="text-md text-muted-foreground">{{ receptionOpen ? '−' : '+' }}</span>
                    </SidebarMenuButton>
                </SidebarMenuItem>
                <SidebarMenu v-if="receptionOpen">
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child class="pl-6" @click.stop.prevent>
                            <Link :href="frontdeskDashboard()" @click.stop.prevent>
                                <ConciergeBell class="h-4 w-4" />
                                <span>Frontdesk</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child class="pl-6" @click.stop.prevent>
                            <Link href="/reservations" @click.stop.prevent>
                                <CalendarDays class="h-4 w-4" />
                                <span>Réservation</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarMenu>
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
