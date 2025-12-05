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
import { edit as settingsProfile } from '@/routes/profile';
import { index as activityIndex } from '@/routes/activity/index';
// import { index as activityIndex } from '@/routes/activity';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    BedDouble,
    Building2,
    LayoutGrid,
    Layers,
    Percent,
    ShoppingBag,
    Sparkles,
    Tags,
    UserCog,
    ConciergeBell,
    Users,
    UsersRound,
    CalendarDays,
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { computed, ref } from 'vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: appDashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Frontdesk',
        href: frontdeskDashboard(),
        icon: ConciergeBell,
    },
];

const ressourcesNavItems: NavItem[] = [
    {
        title: 'Hôtel',
        href: '/ressources/hotel',
        icon: Building2,
    },
    {
        title: 'Types de chambres',
        href: '/ressources/room-types',
        icon: Layers,
    },
    {
        title: 'Chambres',
        href: '/ressources/rooms',
        icon: BedDouble,
    },
    {
        title: 'Offres',
        href: '/ressources/offers',
        icon: Sparkles,
    },
    {
        title: 'Taxes',
        href: '/ressources/taxes',
        icon: Percent,
    },
    {
        title: 'Catégories de produits',
        href: '/ressources/product-categories',
        icon: Tags,
    },
    {
        title: 'Produits',
        href: '/ressources/products',
        icon: ShoppingBag,
    },
    {
        title: 'Utilisateurs (tenant)',
        href: '/ressources/users',
        icon: UserCog,
    }
];

const page = usePage();
const currentUrl = computed(() => (page?.url ? page.url : page.value.url) || '');

const ressourcesOpen = ref(currentUrl.value.startsWith('/ressources/'));
const receptionOpen = ref(currentUrl.value.startsWith('/guests') || currentUrl.value.startsWith('/frontdesk'));

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
                        <Link :href="appDashboard()">
                        <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <SidebarMenu class="px-2">
                <SidebarMenuItem>
                    <SidebarMenuButton
                        class="justify-between"
                        type="button"
                        @click.stop="ressourcesOpen = !ressourcesOpen"
                    >
                        <div class="flex items-center gap-2">
                            <Layers class="h-4 w-4" />
                            <span>Ressources</span>
                        </div>
                        <span class="text-md text-muted-foreground">{{ ressourcesOpen ? '−' : '+' }}</span>
                    </SidebarMenuButton>
                </SidebarMenuItem>
                <SidebarMenu v-if="ressourcesOpen">
                    <SidebarMenuItem v-for="item in ressourcesNavItems" :key="item.title">
                        <SidebarMenuButton as-child class="pl-6" @click.stop.prevent>
                            <Link :href="item.href" @click.stop.prevent>
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarMenu>
            <SidebarMenu class="px-2">
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
                            <Link href="/guests" @click.stop.prevent>
                                <UsersRound class="h-4 w-4" />
                                <span>Clients</span>
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
