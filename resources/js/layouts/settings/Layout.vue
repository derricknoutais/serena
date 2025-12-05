<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Separator } from '@/components/ui/separator';
import { toUrl, urlIsActive } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { index as rolesIndex } from '@/routes/settings/roles';
import { show } from '@/routes/two-factor';
import { edit as editPassword } from '@/routes/user-password';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { AppPageProps } from '@/types';

const page = usePage<AppPageProps>();

const canManageRoles = computed(() =>
    (page.props.auth.user?.roles ?? []).some((role) => ['owner', 'admin'].includes(role.name)),
);

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
]);

const currentPath = typeof window !== undefined ? window.location.pathname : '';
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
