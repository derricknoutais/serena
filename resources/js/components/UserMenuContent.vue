<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Hotel, LogOut, Settings } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    user: User;
}

const handleLogout = () => {
    router.flushAll();
};

const props = defineProps<Props>();

const page = usePage();

const hotels = computed(() => page.props.auth?.hotels || []);
const activeHotel = computed(() => page.props.auth?.activeHotel || null);
const switching = ref(false);

const switchHotel = (hotelId: number) => {
    switching.value = true;
    router.post(
        '/ressources/active-hotel',
        { hotel_id: hotelId },
        {
            preserveScroll: true,
            onFinish: () => {
                switching.value = false;
            },
        },
    );
};
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <div v-if="hotels.length" class="px-2 pb-1 text-xs uppercase text-gray-500">Hôtel actif</div>
        <DropdownMenuItem
            v-for="hotel in hotels"
            :key="hotel.id"
            class="flex items-center justify-between"
            @click="switchHotel(hotel.id)"
        >
            <div class="flex items-center gap-2">
                <Hotel class="mr-2 h-4 w-4" />
                <span>{{ hotel.name }}</span>
            </div>
            <span
                v-if="activeHotel && activeHotel.id === hotel.id"
                class="text-xs font-medium text-indigo-600"
            >
                Actif
            </span>
        </DropdownMenuItem>
        <DropdownMenuSeparator v-if="hotels.length" />
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full" :href="edit()" prefetch as="button">
                <Settings class="mr-2 h-4 w-4" />
                Paramètres
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Déconnexion
        </Link>
    </DropdownMenuItem>
</template>
