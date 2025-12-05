<template>
    <AppLayout title="Frontdesk">
        <div class="space-y-6">
            <div class="flex flex-wrap items-center gap-2 rounded-xl bg-white p-2 shadow-sm">
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    type="button"
                    class="rounded-lg px-4 py-2 text-sm font-semibold transition"
                    :class="activeTab === tab.value ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                    @click="activeTab = tab.value"
                >
                    {{ tab.label }}
                </button>
            </div>

            <ReservationsPlanner
                v-if="activeTab === 'planning'"
                v-bind="reservationsData"
            />
            <RoomBoard
                v-else-if="activeTab === 'rooms'"
                v-bind="roomBoardData"
            />
            <OperationsBoard v-else />
        </div>
    </AppLayout>
</template>

<script>
    import AppLayout from '@/layouts/AppLayout.vue';
    import ReservationsPlanner from '@/components/Frontdesk/ReservationsPlanner.vue';
    import RoomBoard from '@/components/Frontdesk/RoomBoard.vue';
    import OperationsBoard from '@/components/Frontdesk/OperationsBoard.vue';

    export default {
        name: 'FrontDesk',
        components: {
            AppLayout,
            ReservationsPlanner,
            RoomBoard,
            OperationsBoard,
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
        },
        data() {
            return {
                activeTab: 'planning',
                tabs: [
                    { value: 'planning', label: 'Planning & Réservations' },
                    { value: 'rooms', label: 'Room Board' },
                    { value: 'operations', label: 'Arrivées / Départs' },
                ],
            };
        },
    };
</script>
