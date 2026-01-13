<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Separator } from '@/components/ui/separator';
import { toUrl, urlIsActive } from '@/lib/utils';
import { edit as editProfile } from '@/routes/profile';
import { index as rolesIndex } from '@/routes/settings/roles';
import { edit as editPassword } from '@/routes/user-password';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { AppPageProps } from '@/types';

const page = usePage<AppPageProps>();

const canManageRoles = computed(() =>
    (page.props.auth.user?.roles ?? []).some((role) => ['owner', 'admin', 'superadmin'].includes(role.name)),
);
const canManageBadges = computed(() =>
    (page.props.auth.user?.roles ?? []).some((role) => ['owner', 'manager', 'admin', 'superadmin'].includes(role.name)),
);
const canViewResources = computed(() => Boolean(page.props.auth?.can?.resources_view ?? false));
const permissions = computed(() => page.props.auth?.can ?? {});

const sidebarNavItems = computed<NavItem[]>(() => [
    {
        title: 'Profil',
        href: editProfile(),
    },
    {
        title: 'Mot de passe',
        href: editPassword(),
    },
    // {
    //     title: 'Authentification à deux facteurs',
    //     href: show(),
    // },
    // {
    //     title: 'Apparence',
    //     href: editAppearance(),
    // },
    ...(canManageRoles.value
        ? [
            {
                title: 'Rôles & permissions',
                href: rolesIndex().url,
            } satisfies NavItem,
        ]
        : []),
    ...(canManageBadges.value
        ? [
            {
                title: 'Badges & QR',
                href: '/settings/badges',
            } satisfies NavItem,
        ]
        : []),
]);

const currentPath = typeof window !== undefined ? window.location.pathname : '';

const resourcesItems = computed<NavItem[]>(() => [
    {
        title: 'Hôtel',
        href: '/settings/resources/hotel',
    },
    ...(permissions.value.room_types_view
        ? [{ title: 'Types de chambres', href: '/settings/resources/room-types' } satisfies NavItem]
        : []),
    ...(permissions.value.rooms_view
        ? [{ title: 'Chambres', href: '/settings/resources/rooms' } satisfies NavItem]
        : []),
    {
        title: 'Checklists HK',
        href: '/settings/resources/housekeeping-checklists',
    },
    ...(permissions.value.guests_view
        ? [{ title: 'Clients', href: '/settings/resources/guests' } satisfies NavItem]
        : []),
    ...(permissions.value.offers_view
        ? [{ title: 'Offres', href: '/settings/resources/offers' } satisfies NavItem]
        : []),
    ...(permissions.value.taxes_view
        ? [{ title: 'Taxes', href: '/settings/resources/taxes' } satisfies NavItem]
        : []),
    ...(permissions.value.payment_methods_view
        ? [{ title: 'Méthodes de paiement', href: '/settings/resources/payment-methods' } satisfies NavItem]
        : []),
    ...(permissions.value.maintenance_types_manage
        ? [{ title: 'Types de maintenance', href: '/settings/resources/maintenance-types' } satisfies NavItem]
        : []),
    ...(permissions.value.maintenance_technicians_manage
        ? [{ title: 'Techniciens', href: '/settings/resources/technicians' } satisfies NavItem]
        : []),
    ...(permissions.value.product_categories_view
        ? [{ title: 'Catégories de produits', href: '/settings/resources/product-categories' } satisfies NavItem]
        : []),
    ...(permissions.value.products_view
        ? [{ title: 'Produits', href: '/settings/resources/products' } satisfies NavItem]
        : []),
    {
        title: 'Utilisateurs',
        href: '/settings/resources/users',
    },
    {
        title: 'Journal d’activités',
        href: '/settings/resources/activity',
    },
]);

const isResourcesActive = computed(() => currentPath.startsWith('/settings/resources'));
</script>

<template>
    <div class="px-4 py-6">
        <Heading title="Paramètres" description="Gérez votre profil et les paramètres de votre compte" />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-full lg:w-48">
                <nav class="flex flex-col space-y-2">
                    <Link
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        :href="item.href"
                        class="flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-medium transition hover:bg-serena-primary-soft hover:text-serena-primary"
                        :class="urlIsActive(item.href, currentPath) ? 'bg-serena-primary-soft text-serena-primary' : 'text-serena-text-muted'"
                    >
                        <component v-if="item.icon" :is="item.icon" class="h-4 w-4" />
                        {{ item.title }}
                    </Link>
                    <details
                        v-if="canViewResources"
                        class="group rounded-2xl border border-serena-border/50 bg-white/70 px-3 py-2 text-sm"
                        :open="isResourcesActive"
                    >
                        <summary class="flex cursor-pointer items-center justify-between font-semibold text-serena-text-main">
                            <span>Ressources</span>
                            <span class="text-xs text-serena-text-muted group-open:rotate-180">▾</span>
                        </summary>
                        <div class="mt-2 flex flex-col gap-1">
                            <Link
                                v-for="item in resourcesItems"
                                :key="toUrl(item.href)"
                                :href="item.href"
                                class="rounded-full px-3 py-1.5 text-sm transition hover:bg-serena-primary-soft hover:text-serena-primary"
                                :class="urlIsActive(item.href, currentPath) ? 'bg-serena-primary-soft text-serena-primary' : 'text-serena-text-muted'"
                            >
                                {{ item.title }}
                            </Link>
                        </div>
                    </details>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="flex-1 md:max-w-full">
                <section class="max-w-full space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
