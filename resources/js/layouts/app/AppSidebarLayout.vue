<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import OnboardingTour from '@/components/OnboardingTour.vue';
import { usePage } from '@inertiajs/vue3';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const isOwner =
    (page?.props?.auth as any)?.user?.roles?.some((r: any) => r.name === 'owner') ??
    (page?.props.value?.auth as any)?.user?.roles?.some((r: any) => r.name === 'owner') ??
    false;
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <OnboardingTour :is-owner="isOwner" />
    </AppShell>
</template>
