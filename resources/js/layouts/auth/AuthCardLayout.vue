<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Card from '@/components/Card.vue';
import { home } from '@/routes';
import { Link } from '@inertiajs/vue3';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-serena-bg-soft p-6 md:p-10">
        <div class="flex w-full max-w-md flex-col gap-6">
            <Link
                :href="home()"
                class="flex items-center gap-2 self-center font-semibold text-serena-text-main"
            >
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-serena-card shadow-sm">
                    <AppLogoIcon class="size-7 fill-current text-serena-primary" />
                </div>
                Serena
            </Link>

            <Card>
                <div class="px-8 pt-8 text-center">
                    <h1 class="text-xl font-semibold text-serena-text-main">{{ title }}</h1>
                    <p class="mt-2 text-sm text-serena-text-muted">
                        {{ description }}
                    </p>
                </div>
                <div class="px-8 py-8">
                    <slot />
                </div>
            </Card>
        </div>
    </div>
</template>
