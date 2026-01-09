<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { logout } from '@/routes';
import { edit } from '@/routes/profile/index';
import type { User } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Hotel, LogOut, Settings, Users } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

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
// Capture le flash au chargement pour éviter qu'il ne disparaisse après navigation Inertia.
const initialHotelNotice = ref(page.props.auth?.hotelNotice || null);
const switching = ref(false);
const noticeShown = ref(false);

const switchHotel = (hotelId: number) => {
    switching.value = true;
    let timerInterval: ReturnType<typeof setInterval> | undefined;

    router.post(
        '/settings/resources/active-hotel',
        { hotel_id: hotelId },
        {
            preserveScroll: true,
            onSuccess: () => {
                const selected = hotels.value.find((h) => h.id === hotelId);
                const hotelName = selected?.name ?? 'cet hôtel';

                Swal.fire({
                    icon: 'info',
                    title: 'Hôtel actif',
                    html: `Toutes vos actions sont désormais liées à l’hôtel "<b>${hotelName}</b>".<br/><small>Redirection automatique...</small>`,
                    timer: 5200,
                    timerProgressBar: true,
                    didOpen: () => {
                        Swal.showLoading();
                        timerInterval = setInterval(() => {
                            const timer = Swal.getHtmlContainer()?.querySelector('b.timer');
                            if (timer) {
                                timer.textContent = `${Swal.getTimerLeft()}`;
                            }
                        }, 100);
                    },
                    willClose: () => {
                        if (timerInterval) {
                            clearInterval(timerInterval);
                        }
                    },
                }).then(() => {
                    router.visit('/dashboard', { replace: true });
                });
            },
            onFinish: () => {
                switching.value = false;
            },
        },
    );
};

onMounted(() => {
    if (initialHotelNotice.value && !noticeShown.value) {
        Swal.fire({
            icon: 'info',
            title: 'Hôtel actif',
            text: initialHotelNotice.value as string,
            confirmButtonText: 'OK',
            customClass: {
                confirmButton: 'bg-indigo-600 text-white px-4 py-2 rounded-md',
            },
        });
        noticeShown.value = true;
    }
});
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
    <DropdownMenuSeparator v-if="hotelNotice" />
    <div v-if="hotelNotice" class="px-3 pb-2 text-xs text-indigo-700">
        {{ hotelNotice }}
    </div>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link class="block w-full" href="/switch-user" prefetch as="button">
            <Users class="mr-2 h-4 w-4" />
            Changer d'utilisateur
        </Link>
    </DropdownMenuItem>
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
