<template>
    <AppLayout title="Frontdesk">
        <div class="mb-4">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Opérations
            </h2>
        </div>
        
        <div class="mb-4 flex w-full items-center justify-between rounded-lg bg-white p-3 shadow-sm border border-gray-100">
             <div class="text-sm font-semibold text-gray-700">
                Caisse FrontDesk
             </div>
             <CashIndicator type="frontdesk" />
        </div>

        <OperationsBoard />
    </AppLayout>
</template>

<script>
    import AppLayout from '@/layouts/AppLayout.vue';
    import OperationsBoard from '@/components/Frontdesk/OperationsBoard.vue';
    import CashIndicator from '@/Components/CashIndicator.vue';

    export default {
        name: 'FrontdeskDashboardPage',
        components: {
            AppLayout,
            OperationsBoard,
            CashIndicator,
        },
    };
</script>
