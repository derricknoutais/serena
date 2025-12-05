<script lang="ts">
import { defineComponent, type PropType } from 'vue';
import { Link } from '@inertiajs/vue3';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import HeaderUserMenu from '@/components/HeaderUserMenu.vue';
import type { BreadcrumbItemType } from '@/types';

export default defineComponent({
    name: 'AppHeader',
    components: {
        Breadcrumbs,
        HeaderUserMenu,
        Link,
    },
    props: {
        breadcrumbs: {
            type: Array as PropType<BreadcrumbItemType[]>,
            default: () => [],
        },
    },
});
</script>

<template>
    <header class="border-b border-serena-border bg-serena-card shadow-sm">
        <div class="page-container flex h-16 items-center justify-between">
            <div class="flex items-center space-x-2">
                <div class="h-3 w-3 rounded-full bg-serena-primary"></div>
                <span class="text-lg font-semibold text-serena-text-main">Serena</span>
            </div>
            <nav class="flex items-center space-x-4 text-sm">
                <Link
                    href="/rooms/board"
                    class="rounded-full px-3 py-1 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                >
                    Rooms
                </Link>
                <Link
                    href="/guests"
                    class="rounded-full px-3 py-1 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                >
                    Guests
                </Link>
                <Link
                    href="/reservations"
                    class="rounded-full px-3 py-1 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                >
                    Reservations
                </Link>
                <HeaderUserMenu />
            </nav>
        </div>
        <div class="page-container py-3" v-if="breadcrumbs.length">
            <Breadcrumbs :breadcrumbs="breadcrumbs" />
        </div>
    </header>
</template>
