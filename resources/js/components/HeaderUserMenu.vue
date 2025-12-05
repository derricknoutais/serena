<script lang="ts">
import { defineComponent, computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { ChevronsUpDown } from 'lucide-vue-next';

export default defineComponent({
    name: 'HeaderUserMenu',
    components: {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuTrigger,
        UserInfo,
        UserMenuContent,
        ChevronsUpDown,
    },
    computed: {
        currentUser() {
            return (this.$page?.props as any)?.auth?.user ?? null;
        },
    },
});
</script>

<template>
    <DropdownMenu v-if="currentUser">
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="flex items-center gap-2 rounded-full border border-serena-border bg-serena-primary-soft/30 px-3 py-1.5 text-sm font-medium text-serena-text-main transition hover:bg-serena-primary-soft focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-serena-primary-soft"
            >
                <UserInfo :user="currentUser" />
                <ChevronsUpDown class="h-4 w-4 text-serena-text-muted" />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="end"
            class="w-64 rounded-xl border border-serena-border/60 bg-white p-2 shadow-lg"
        >
            <UserMenuContent :user="currentUser" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
