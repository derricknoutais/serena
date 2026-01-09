<template>
    <AppLayout>
        <SettingsLayout v-if="useSettingsLayout">
            <div class="rounded-xl bg-white p-4 shadow-sm lg:p-6">
                <slot />
            </div>
        </SettingsLayout>
        <div v-else class="rounded-xl bg-white p-4 shadow-sm lg:p-6">
            <slot />
        </div>
    </AppLayout>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

export default defineComponent({
    name: 'ConfigLayout',
    components: { AppLayout, SettingsLayout },
    computed: {
        useSettingsLayout(): boolean {
            return Boolean((this as any)?.$page?.props?.ui?.settings_resources_layout ?? false);
        },
    },
});
</script>
