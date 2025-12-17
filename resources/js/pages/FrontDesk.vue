<template>
    <AppLayout title="Frontdesk">
        <div class="space-y-6">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Arrivées aujourd’hui</p>
                    <p class="mt-2 text-2xl font-bold text-gray-800">{{ quickStats.arrivalsToday }}</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Départs aujourd’hui</p>
                    <p class="mt-2 text-2xl font-bold text-gray-800">{{ quickStats.departuresToday }}</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">En séjour</p>
                    <p class="mt-2 text-2xl font-bold text-gray-800">{{ quickStats.inHouse }}</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Caisse FrontDesk</p>
                    </div>
                    <CashIndicator type="frontdesk" />
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 rounded-xl bg-white p-2 shadow-sm">
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    type="button"
                    class="rounded-lg px-4 py-2 text-sm font-semibold transition cursor-pointer"
                    :class="activeTab === tab.value ? 'bg-serena-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                    @click="activeTab = tab.value"
                >
                    {{ tab.label }}
                </button>
            </div>

            <div class="space-y-6">
                <ReservationsPlanner
                    v-if="activeTab === 'planning'"
                    v-bind="reservationsData"
                />
                <RoomBoard
                    v-else-if="activeTab === 'rooms'"
                    v-bind="roomBoardData"
                    :can-manage-housekeeping="roomBoardData.canManageHousekeeping"
                />
                <OccupancyForecast
                    v-else-if="activeTab === 'forecast'"
                    :initial-forecast="forecastData"
                    :can-view="canViewForecast"
                />
                <OperationsBoard v-else />
            </div>
        </div>
    </AppLayout>
</template>

<script>
    import AppLayout from '@/layouts/AppLayout.vue';
    import ReservationsPlanner from '@/components/Frontdesk/ReservationsPlanner.vue';
    import RoomBoard from '@/components/Frontdesk/RoomBoard.vue';
    import OperationsBoard from '@/components/Frontdesk/OperationsBoard.vue';
    import CashIndicator from '@/components/CashIndicator.vue';
    import OccupancyForecast from '@/components/Frontdesk/OccupancyForecast.vue';

    export default {
        name: 'FrontDesk',
        components: {
            AppLayout,
            ReservationsPlanner,
            RoomBoard,
            OperationsBoard,
            CashIndicator,
            OccupancyForecast,
        },
        props: {
            reservationsData: {
                type: Object,
                required: true,
            },
            roomBoardData: {
                type: Object,
                required: true,
            },
            forecastData: {
                type: Object,
                default: null,
            },
        },
        data() {
            return {
                activeTab: 'planning',
                tabs: [
                    { value: 'planning', label: 'Planning & Réservations' },
                    { value: 'rooms', label: 'Room Board' },
                    { value: 'forecast', label: 'Prévision' },
                    { value: 'operations', label: 'Arrivées / Départs' },
                ],
            };
        },
        computed: {
            canViewForecast() {
                return this.$page?.props?.auth?.can?.night_audit_view ?? false;
            },
            quickStats() {
                const events = this.reservationsData?.events || [];
                const today = new Date().toISOString().slice(0, 10);

                const arrivalsToday = events.filter((e) => (e.check_in_date || '').slice(0, 10) === today).length;
                const departuresToday = events.filter((e) => (e.check_out_date || '').slice(0, 10) === today).length;
                const inHouse = events.filter((e) => e.status === 'in_house').length;

                return {
                    arrivalsToday,
                    departuresToday,
                    inHouse,
                };
            },
        },
    };
</script>
