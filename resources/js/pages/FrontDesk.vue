<template>
    <AppLayout title="Frontdesk">
        <div class="space-y-6">
            <div class="grid gap-4">
                <div class="flex flex-col gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm xl:p-3">
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
                    @click="setActiveTab(tab.value)"
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
                <OperationsBoard
                    v-else
                    :guests="reservationsData.guests"
                    :offers="reservationsData.offers"
                    :rooms="reservationsData.rooms"
                />
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
                activeTab: 'operations',
                tabs: [
                    { value: 'operations', label: 'Arrivées / Départs' },
                    { value: 'planning', label: 'Planning & Réservations' },
                    { value: 'rooms', label: 'Room Board' },
                    { value: 'forecast', label: 'Prévision' },
                ],
            };
        },
        mounted() {
            const initialTab = this.getInitialTab();

            if (initialTab) {
                this.activeTab = initialTab;
            }
        },
        computed: {
            validTabs() {
                return this.tabs.map((tab) => tab.value);
            },
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
        methods: {
            setActiveTab(tab) {
                if (!this.validTabs.includes(tab)) {
                    return;
                }

                this.activeTab = tab;
                localStorage.setItem('frontdesk.activeTab', tab);
                this.replaceTabInUrl(tab);
            },
            getInitialTab() {
                const fromUrl = this.getTabFromUrl();
                if (fromUrl && this.validTabs.includes(fromUrl)) {
                    return fromUrl;
                }

                const fromStorage = localStorage.getItem('frontdesk.activeTab');
                if (fromStorage && this.validTabs.includes(fromStorage)) {
                    this.replaceTabInUrl(fromStorage);

                    return fromStorage;
                }

                return null;
            },
            getTabFromUrl() {
                const params = new URLSearchParams(window.location.search);

                return params.get('tab');
            },
            replaceTabInUrl(tab) {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', tab);
                window.history.replaceState({}, '', url);
            },
        },
    };
</script>
