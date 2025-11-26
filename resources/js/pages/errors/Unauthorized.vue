<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-background px-6 text-center text-foreground">
        <Head title="Accès refusé" />
        <div class="max-w-md space-y-4">
            <p class="text-sm uppercase tracking-wide text-muted-foreground">
                Accès refusé
            </p>
            <h1 class="text-2xl font-semibold">Vous n'avez pas la permission.</h1>
            <p class="text-muted-foreground">
                Vous n'avez pas les droits nécessaires pour effectuer cette action. Si vous pensez que c'est une erreur,
                contactez un administrateur.
            </p>
        </div>
    </div>
</template>
