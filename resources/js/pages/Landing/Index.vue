<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    canRegister: boolean;
}>();
</script>

<template>
    <Head title="Serena — Hotel Management Reimagined" />

    <div class="min-h-screen bg-serena-bg-soft">
        <div
            class="page-container flex min-h-screen flex-col items-center justify-center gap-16 py-20 lg:flex-row lg:gap-24"
        >
            <div class="max-w-xl space-y-6 text-center lg:text-left">
                <p class="text-sm font-semibold tracking-wider text-serena-primary">
                    Modern Property Management
                </p>
                <h1 class="text-4xl font-semibold text-serena-text-main">
                    Serena — Hotel Management Reimagined.
                </h1>
                <p class="text-lg text-serena-text-muted">
                    A modern, minimalist PMS designed for speed, clarity, and simplicity.
                    Stay focused on delighting guests while Serena takes care of the details.
                </p>
                <div class="flex flex-col gap-3 sm:flex-row sm:justify-start sm:text-left">
                    <Link
                        href="/login"
                        class="inline-flex items-center justify-center rounded-full bg-serena-primary px-6 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-serena-primary-dark"
                    >
                        Login
                    </Link>
                    <Link
                        v-if="canRegister"
                        href="/register"
                        class="inline-flex items-center justify-center rounded-full border border-serena-border bg-white px-6 py-2 text-sm font-medium text-serena-text-main transition hover:bg-serena-primary-soft"
                    >
                        Create Account
                    </Link>
                </div>
            </div>

            <div class="w-full max-w-xl">
                <div
                    class="relative rounded-3xl border border-serena-border/60 bg-white p-8 shadow-md"
                >
                    <div class="absolute inset-0 bg-gradient-to-br from-serena-primary-soft via-transparent to-white opacity-80"></div>
                    <div class="relative space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-serena-text-muted">Occupancy</span>
                            <span class="text-2xl font-semibold text-serena-text-main">87%</span>
                        </div>
                        <div class="h-2 rounded-full bg-serena-primary-soft">
                            <div class="h-2 w-4/5 rounded-full bg-serena-primary"></div>
                        </div>
                        <div class="grid gap-4 rounded-2xl bg-serena-primary-soft/60 p-4">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-serena-text-muted">Upcoming arrivals</span>
                                <span class="font-semibold text-serena-text-main">24</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-serena-text-muted">In-house guests</span>
                                <span class="font-semibold text-serena-text-main">68</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-serena-text-muted">Pending tasks</span>
                                <span class="font-semibold text-serena-accent">12</span>
                            </div>
                        </div>
                        <p class="text-sm text-serena-text-muted">
                            Serena keeps every department aligned with a clean, unified workspace.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
