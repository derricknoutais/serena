<script lang="ts">
import { defineComponent, type PropType } from 'vue';
import { Link } from '@inertiajs/vue3';

import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import HeaderUserMenu from '@/components/HeaderUserMenu.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import { dashboard as frontdeskDashboard } from '@/routes/frontdesk';
import type { BreadcrumbItemType } from '@/types';


export default defineComponent({
    name: 'AppLayout',
    components: {
        AppLogoIcon,
        Breadcrumbs,
        HeaderUserMenu,
        Link,
        PrimaryButton,
    },
    data() {
        return {
            resourcesOpen: false as boolean,
        };
    },
    props: {
        breadcrumbs: {
            type: Array as PropType<BreadcrumbItemType[]>,
            default: () => [],
        },
    },
    methods: {
        frontdeskDashboard,
    },
    computed: {
        maintenanceLinkVisible(): boolean {
            const roles = (this.$page?.props?.auth?.user?.roles ?? []) as Array<{ name: string }>;
            const allowed = ['owner', 'manager', 'maintenance', 'superadmin'];

            return roles.some((role) => allowed.includes(role.name));
        },
    },
});
</script>

<template>
    <div class="min-h-screen bg-serena-bg-soft text-serena-text-main">
        <header class="border-b border-serena-border bg-serena-card shadow-sm">
            <div class="page-container flex h-16 items-center justify-between">
                <div class="flex items-center space-x-2">
                    <Link
                        href="/"
                        class="flex flex-col items-center gap-2 font-medium"
                    >
                        <div
                            class="mb-1 flex h-16 w-16 items-center justify-center rounded-md"
                        >
                            <AppLogoIcon
                                class="size-16 fill-current text-[var(--foreground)] dark:text-white"
                            />
                        </div>
                        <span class="sr-only">Serena</span>
                    </Link>
                </div>

                <nav class="flex items-center space-x-4 text-sm">
                    <!-- Ressources -->
                    <div class="relative" aria-label="Ressources">
                        <PrimaryButton
                            type="button"
                            variant="primary"
                            class="bg-serena-primary/90 text-xs font-medium text-white hover:bg-serena-primary-dark"
                            @click="resourcesOpen = !resourcesOpen"
                        >
                            <span>Ressources</span>
                            <span class="text-[10px] opacity-80">
                                {{ resourcesOpen ? '▴' : '▾' }}
                            </span>
                        </PrimaryButton>
                        <div
                            v-if="resourcesOpen"
                            class="absolute right-0 z-20 mt-2 w-56 rounded-xl border border-serena-border/60 bg-serena-card p-2 text-sm shadow-md"
                        >
                            <Link
                                href="/ressources/hotel"
                                class="block rounded-lg px-3 py-1.5 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                            >
                                Hôtel
                            </Link>
                            <Link
                                href="/ressources/room-types"
                                class="block rounded-lg px-3 py-1.5 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                            >
                                Types de chambres
                            </Link>
                            <Link
                                href="/ressources/rooms"
                                class="block rounded-lg px-3 py-1.5 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                            >
                                Chambres
                            </Link>
                            <Link
                                href="/ressources/offers"
                                class="block rounded-lg px-3 py-1.5 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                            >
                                Offres
                            </Link>
                            <Link
                                href="/ressources/taxes"
                                class="block rounded-lg px-3 py-1.5 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                            >
                                Taxes
                            </Link>
                            <Link
                                href="/ressources/product-categories"
                                class="block rounded-lg px-3 py-1.5 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                            >
                                Catégories de produits
                            </Link>
                            <Link
                                href="/ressources/products"
                                class="block rounded-lg px-3 py-1.5 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                            >
                                Produits
                            </Link>
                            <Link
                                href="/ressources/users"
                                class="block rounded-lg px-3 py-1.5 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                            >
                                Utilisateurs
                            </Link>
                        </div>
                    </div>
                    <Link
                        href="/guests"
                        class="rounded-full px-3 py-1 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                    >
                        Guests
                    </Link>
                    <Link
                        :href="frontdeskDashboard()"
                        class="rounded-full px-3 py-1 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                    >
                        FrontDesk
                    </Link>
                    <Link
                        href="/housekeeping"
                        class="rounded-full px-3 py-1 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                    >
                        Housekeeping
                    </Link>
                    <Link
                        v-if="maintenanceLinkVisible"
                        href="/maintenance"
                        class="rounded-full px-3 py-1 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                    >
                        Maintenance
                    </Link>
                    <Link
                        v-if="maintenanceLinkVisible"
                        href="/pos"
                        class="rounded-full px-3 py-1 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                    >
                        Point de Vente
                    </Link>

                    <slot name="user-menu">
                        <HeaderUserMenu />
                    </slot>
                </nav>
            </div>
        </header>

        <main class="py-6">
            <div class="page-container space-y-4">
                <Breadcrumbs
                    v-if="breadcrumbs.length"
                    :breadcrumbs="breadcrumbs"
                />
                <slot />
            </div>
        </main>
    </div>
</template>
