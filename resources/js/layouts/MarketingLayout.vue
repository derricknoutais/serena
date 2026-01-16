<template>
    <div class="min-h-screen bg-white text-serena-text-main">
        <header class="sticky top-0 z-30 h-16 shrink-0 border-b border-serena-border/60 bg-white/90 backdrop-blur">
            <div class="page-container flex h-16 items-center justify-between gap-3">
                <div class="flex max-w-16 items-center gap-2">
                    <AppLogoIcon class="size-6 shrink-0 fill-current text-serena-primary" />
                    <span class="truncate text-base font-semibold sm:text-lg">Serena</span>
                </div>
                <div class="flex shrink-0 items-center gap-2 text-sm font-medium">
                    <Link
                        href="/login"
                        class="whitespace-nowrap rounded-full px-3 py-2 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary sm:px-4"
                    >
                        Se connecter
                    </Link>
                    <Link
                        v-if="canRegister"
                        href="/register"
                        class="whitespace-nowrap rounded-full border border-serena-border bg-white px-3 py-2 text-serena-text-main transition hover:bg-serena-primary-soft sm:px-4"
                    >
                        Créer un compte
                    </Link>
                </div>
            </div>
        </header>

        <main class="pt-2">
            <slot />
        </main>

        <footer class="border-t border-serena-border/60 bg-white">
            <div class="page-container flex flex-col items-center justify-between gap-2 py-6 text-sm text-serena-text-muted sm:flex-row">
                <span>© {{ new Date().getFullYear() }} Serena. Tous droits réservés.</span>
                <span>Contact : hello@serena.app</span>
            </div>
        </footer>
    </div>
</template>

<script>
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

export default {
    name: 'MarketingLayout',
    components: {
        AppLogoIcon,
        Link,
    },
    props: {
        canRegister: {
            type: Boolean,
            default: false,
        },
    },
};
</script>
