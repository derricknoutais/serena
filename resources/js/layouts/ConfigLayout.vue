<template>
    <AppLayout>
        <div class="rounded-xl bg-white p-4 shadow-sm lg:p-6">
            <slot />
        </div>
    </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';

export default {
    name: 'ConfigLayout',
    components: { AppLayout },
};
</script>
