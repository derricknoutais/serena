<template>
    <ConfigLayout>
        <ReservationsPlanner v-bind="$props" />
    </ConfigLayout>
</template>

<script>
    import ConfigLayout from '@/layouts/ConfigLayout.vue';
    import ReservationsPlanner from '@/components/Frontdesk/ReservationsPlanner.vue';

    export default {
        name: 'ReservationsIndex',
        components: { ConfigLayout, ReservationsPlanner },
        props: {
            events: {
                type: Array,
                required: true,
            },
            guests: {
                type: Array,
                required: true,
            },
            roomTypes: {
                type: Array,
                required: true,
            },
            statusOptions: {
                type: Array,
                required: true,
            },
            defaults: {
                type: Object,
                required: true,
            },
            rooms: {
                type: Array,
                required: true,
            },
            offers: {
                type: Array,
                required: true,
            },
            offerRoomTypePrices: {
                type: Array,
                default: () => [],
            },
            canManageTimes: {
                type: Boolean,
                required: true,
            },
            canExtendStay: {
                type: Boolean,
                required: true,
            },
            canShortenStay: {
                type: Boolean,
                required: true,
            },
            canChangeRoom: {
                type: Boolean,
                required: true,
            },
        },
    };
</script>
