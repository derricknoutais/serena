<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
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
        title: 'Profile',
        href: editProfile(),
    },
    {
        title: 'Password',
        href: editPassword(),
    },
    {
        title: 'Two-Factor Auth',
        href: show(),
    },
    {
        title: 'Appearance',
        href: editAppearance(),
    },
    ...(canManageRoles.value
        ? [
            {
                title: 'Roles & Permissions',
                href: rolesIndex().url,
            } satisfies NavItem,
        ]
        : []),
]);

const currentPath = typeof window !== undefined ? window.location.pathname : '';
</script>

<template>
    <div class="px-4 py-6">
        <Heading title="Settings" description="Manage your profile and account settings" />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-full lg:w-48">
                <nav class="flex flex-col space-y-1 space-x-0">
                    <Button v-for="item in sidebarNavItems" :key="toUrl(item.href)" variant="ghost" :class="[
                        'w-full justify-start',
                        { 'bg-muted': urlIsActive(item.href, currentPath) },
                    ]" as-child>
                        <Link :href="item.href">
                        <component v-if="item.icon" :is="item.icon" class="h-4 w-4" />
                        {{ item.title }}
                        </Link>
                    </Button>
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
