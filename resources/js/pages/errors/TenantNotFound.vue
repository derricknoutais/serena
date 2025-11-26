<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    domain: string;
    app_url?: string;
    central_login_url?: string;
    message?: string;
}>();

const centralLink = computed(() => {
    if (props.central_login_url) {
        return props.central_login_url;
    }

    const base = props.app_url ?? '';

    return `${base.replace(/\/$/, '')}/login`;
});
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-background px-6 text-center text-foreground">

        <Head title="Company not found" />
        <div class="max-w-md space-y-3">
            <p class="text-sm uppercase tracking-wide text-muted-foreground">
                Company not found
            </p>
            <h1 class="text-2xl font-semibold">This company does not exist.</h1>
            <p class="text-muted-foreground">
                We could not find a tenant for <strong>{{ domain }}</strong>. Check
                the spelling or contact your administrator.
            </p>
            <a
                :href="centralLink"
                class="inline-flex items-center justify-center rounded-md border border-input bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow transition hover:brightness-110"
            >
                Go to central login
            </a>
        </div>
    </div>
</template>
