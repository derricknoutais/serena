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
import { dashboard } from '@/routes';
import { edit as settingsProfile } from '@/routes/profile';
import { index as activityIndex } from '@/routes/activity/index';
// import { index as activityIndex } from '@/routes/activity';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import {
    Activity,
    BedDouble,
    Building2,
    LayoutGrid,
    Layers,
    Percent,
    ShoppingBag,
    Sparkles,
    UserCog,
    Users,
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { ref } from 'vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Utilisateurs',
        href: settingsProfile(),
        icon: Users,
    }
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

const ressourcesOpen = ref(true);

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
                        <Link :href="dashboard()">
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
                        @click="ressourcesOpen = !ressourcesOpen"
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
                        <SidebarMenuButton as-child>
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
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
