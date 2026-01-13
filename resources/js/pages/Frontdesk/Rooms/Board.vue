<template>
    <AppLayout>
        <RoomBoard v-bind="$props" />
    </AppLayout>
</template>

<script>
    import AppLayout from '@/layouts/AppLayout.vue';
    import RoomBoard from '@/components/Frontdesk/RoomBoard.vue';

    export default {
        name: 'RoomsBoardPage',
        components: {
            AppLayout,
            RoomBoard,
        },
        props: {
            date: {
                type: String,
                required: true,
            },
            roomsByFloor: {
                type: Array,
                required: true,
            },
            walkInRoom: {
                type: Object,
                default: null,
            },
            walkInRoomType: {
                type: Object,
                default: null,
            },
            walkInDefaultDates: {
                type: Object,
                default: null,
            },
            walkInOffers: {
                type: Array,
                default: () => [],
            },
            walkInSource: {
                type: String,
                default: 'walk_in',
            },
            offers: {
                type: Array,
                default: () => [],
            },
            offerRoomTypePrices: {
                type: Array,
                default: () => [],
            },
            paymentMethods: {
                type: Array,
                default: () => [],
            },
            guests: {
                type: Array,
                default: () => [],
            },
            canManageHousekeeping: {
                type: Boolean,
                default: false,
            },
            maintenancePermissions: {
                type: Object,
                default: () => ({
                    canReport: false,
                    canHandle: false,
                    canProgress: false,
                }),
            },
            currentUser: {
                type: Object,
                default: () => ({
                    id: null,
                    name: null,
                }),
            },
            maintenanceTypes: {
                type: Array,
                default: () => [],
            },
        },
    };
</script>
