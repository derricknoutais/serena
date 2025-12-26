<script lang="ts">
import { defineComponent, type PropType } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { listOutbox, processOutbox } from '@/offline/outbox';
import { Head, Link, router } from '@inertiajs/vue3';

import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import HeaderUserMenu from '@/components/HeaderUserMenu.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import { dashboard as frontdeskDashboard } from '@/routes/frontdesk';
import { logout } from '@/routes';
import { edit } from '@/routes/profile/index';
import type { BreadcrumbItemType } from '@/types';


export default defineComponent({
    name: 'AppLayout',
    components: {
        AppLogoIcon,
        Breadcrumbs,
        HeaderUserMenu,
        Head,
        Link,
        PrimaryButton,
    },
    data() {
        return {
            resourcesOpen: false as boolean,
            offline: !navigator.onLine,
            outboxOpen: false,
            outboxItems: [] as Array<any>,
            notificationsOpen: false,
            notificationsLoading: false,
            notifications: [] as Array<any>,
            unreadCount: 0,
            notificationTimer: null as number | null,
            operationsOpen: false,
            financeOpen: false,
            mobileNavOpen: false,
        };
    },
    props: {
        breadcrumbs: {
            type: Array as PropType<BreadcrumbItemType[]>,
            default: () => [],
        },
        title: {
            type: String,
            default: null,
        },
    },
    methods: {
        frontdeskDashboard,
        logout,
        edit,
        formatComponentTitle(component: string): string {
            const segment = component.split('/').pop() ?? component;
            const withSpaces = segment
                .replace(/[-_]+/g, ' ')
                .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
                .replace(/\s+/g, ' ')
                .trim();

            if (!withSpaces) {
                return 'Serena';
            }

            return withSpaces;
        },
        async loadOutbox() {
            this.outboxItems = await listOutbox();
        },
        async syncOutbox() {
            await processOutbox({
                onSuccess: () => this.loadOutbox(),
                onError: () => this.loadOutbox(),
            });
        },
        async loadNotifications(showToast = false) {
            this.notificationsLoading = true;

            try {
                const response = await axios.get('/notifications', {
                    params: { latest: true, limit: 10 },
                    headers: { Accept: 'application/json' },
                });
                const previousUnread = this.unreadCount;
                this.notifications = response.data?.notifications ?? [];
                this.unreadCount = response.data?.unread_count ?? this.unreadCount;

                if (showToast && this.unreadCount > previousUnread) {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        timer: 3000,
                        showConfirmButton: false,
                        icon: 'info',
                        title: 'Nouvelle notification',
                    });
                }
            } catch (error) {
                console.error('Notifications fetch error', error);
            } finally {
                this.notificationsLoading = false;
            }
        },
        async markNotificationRead(id: string) {
            try {
                await axios.patch(`/notifications/${id}`);
                this.loadNotifications();
            } catch (error) {
                console.error(error);
            }
        },
        async markAllNotificationsRead() {
            try {
                await axios.patch('/notifications/read-all');
                this.unreadCount = 0;
                this.loadNotifications();
            } catch (error) {
                console.error(error);
            }
        },
        handleLogout() {
            router.flushAll();
            this.mobileNavOpen = false;
        },
        handleNetworkChange() {
            this.offline = !navigator.onLine;
            if (!this.offline) {
                this.syncOutbox();
            }
        },
    },
    created() {
        this.unreadCount = (this as any)?.$page?.props?.notifications?.unread_count ?? 0;
    },
    mounted() {
        this.loadOutbox();
        this.loadNotifications();
        this.notificationTimer = window.setInterval(() => this.loadNotifications(true), 60000);
        window.addEventListener('online', this.handleNetworkChange);
        window.addEventListener('offline', this.handleNetworkChange);
        this.unreadCount = (this as any)?.$page?.props?.notifications?.unread_count ?? 0;
    },
    beforeUnmount() {
        window.removeEventListener('online', this.handleNetworkChange);
        window.removeEventListener('offline', this.handleNetworkChange);
        if (this.notificationTimer) {
            window.clearInterval(this.notificationTimer);
        }
    },
    computed: {
        resolvedTitle(): string {
            if (this.title) {
                return this.title;
            }

            const breadcrumbTitle = this.breadcrumbs[0]?.title ?? null;
            if (breadcrumbTitle) {
                return breadcrumbTitle;
            }

            const component = (this as any)?.$page?.component ?? '';
            if (component) {
                return this.formatComponentTitle(component);
            }

            return 'Serena';
        },
        maintenanceLinkVisible(): boolean {
            const permissions = this.$page?.props?.auth?.can ?? {};

            return Boolean(permissions.maintenance_tickets_view ?? false);
        },
        posLinkVisible(): boolean {
            const permissions = this.$page?.props?.auth?.can ?? {};

            return Boolean(permissions.pos_view ?? false);
        },
        cashLinkVisible(): boolean {
            const permissions = this.$page?.props?.auth?.can ?? {};

            return Boolean((permissions.cash_sessions_open ?? false) || (permissions.cash_sessions_close ?? false));
        },
        analyticsLinkVisible(): boolean {
            const roles = this.$page?.props?.auth?.user?.roles || [];

            return roles.some((role: any) => ['owner', 'manager', 'superadmin'].includes(role.name));
        },
        currentUser() {
            return (this.$page?.props as any)?.auth?.user ?? null;
        },
        hotels() {
            return (this.$page?.props as any)?.auth?.hotels ?? [];
        },
        activeHotel() {
            return (this.$page?.props as any)?.auth?.activeHotel ?? null;
        },
    },
});
</script>

<template>
    <Head :title="resolvedTitle" />
    <div class="min-h-screen bg-serena-bg-soft text-serena-text-main">
        <div
            v-if="offline"
            class="sticky top-0 z-50 flex items-center justify-between bg-amber-500 px-4 py-2 text-sm font-semibold text-white shadow"
        >
            <span>Hors ligne — les actions seront synchronisées automatiquement</span>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="rounded bg-white/20 px-3 py-1 text-xs font-semibold text-white hover:bg-white/30"
                    @click="outboxOpen = true; loadOutbox();"
                >
                    Voir la file
                </button>
                <button
                    type="button"
                    class="rounded bg-white/20 px-3 py-1 text-xs font-semibold text-white hover:bg-white/30"
                    @click="syncOutbox"
                >
                    Synchroniser
                </button>
            </div>
        </div>
        <header class="border-b border-serena-border bg-serena-card shadow-sm">
            <div class="page-container flex h-16 items-center justify-between">
                <div class="flex items-center space-x-2">
                    <Link
                        href="/"
                        class="flex flex-col items-center gap-2 font-medium"
                    >
                        <div
                            class="mb-1 flex h-16 w-16 items-center justify-center rounded-md"
                        >
                            <AppLogoIcon
                                class="size-16 fill-current text-[var(--foreground)] dark:text-white"
                            />
                        </div>
                        <span class="sr-only">Serena</span>
                    </Link>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-full border border-serena-border/60 bg-white/70 p-2 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary lg:hidden"
                    aria-label="Ouvrir le menu"
                    @click="mobileNavOpen = !mobileNavOpen"
                >
                    <svg v-if="!mobileNavOpen" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <nav class="hidden items-center space-x-4 text-sm lg:flex">
                    <!-- Ressources -->
                    <div class="relative" aria-label="Ressources">
                        <PrimaryButton
                            type="button"
                            variant="primary"
                            class="bg-serena-primary/90 text-xs font-medium text-white hover:bg-serena-primary-dark"
                            @click="resourcesOpen = !resourcesOpen"
                        >
                            <span>Ressources</span>
                            <span class="text-[10px] opacity-80">
                                {{ resourcesOpen ? '▴' : '▾' }}
                            </span>
                        </PrimaryButton>
                        <div
                            v-if="resourcesOpen"
                            class="absolute right-0 z-20 mt-2 w-56 rounded-xl border border-serena-border/60 bg-serena-card p-2 text-sm shadow-md"
                        >
                            <Link
                                href="/ressources/hotel"
                                class="block rounded-lg px-3 py-1.5 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                            >
                                Hôtel
                            </Link>
                            <Link
                                href="/ressources/room-types"
                                class="block rounded-lg px-3 py-1.5 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                            >
                                Types de chambres
                            </Link>
                            <Link
                                href="/ressources/rooms"
                                class="block rounded-lg px-3 py-1.5 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                            >
                                Chambres
                            </Link>
                            <Link
                                href="/ressources/offers"
                                class="block rounded-lg px-3 py-1.5 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                            >
                                Offres
                            </Link>
                            <Link
                                href="/ressources/taxes"
                                class="block rounded-lg px-3 py-1.5 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                            >
                                Taxes
                            </Link>
                            <Link
                                href="/ressources/payment-methods"
                                class="block rounded-lg px-3 py-1.5 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                            >
                                Méthodes de paiement
                            </Link>
                            <Link
                                href="/ressources/product-categories"
                                class="block rounded-lg px-3 py-1.5 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                            >
                                Catégories de produits
                            </Link>
                            <Link
                                href="/ressources/products"
                                class="block rounded-lg px-3 py-1.5 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                            >
                                Produits
                            </Link>
                            <Link
                                href="/ressources/users"
                                class="block rounded-lg px-3 py-1.5 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                            >
                                Utilisateurs
                            </Link>
                            <Link
                                href="/activity"
                                class="block rounded-lg px-3 py-1.5 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                            >
                                Journal d’activités
                            </Link>
                        </div>
                    </div>
                    <Link
                        href="/guests"
                        class="rounded-full px-3 py-1 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                    >
                        Guests
                    </Link>
                    <Link
                        :href="frontdeskDashboard()"
                        class="rounded-full px-3 py-1 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                    >
                        FrontDesk
                    </Link>
                    <div class="relative">
                        <button
                            type="button"
                            class="rounded-full px-3 py-1 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                            @click="operationsOpen = !operationsOpen"
                        >
                            Opérations ▾
                        </button>
                        <div
                            v-if="operationsOpen"
                            class="absolute right-0 z-30 mt-2 w-44 rounded-xl border border-serena-border bg-white shadow-lg"
                        >
                            <Link
                                href="/housekeeping"
                                class="block px-3 py-2 text-sm text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                            >
                                Housekeeping
                            </Link>
                            <Link
                                v-if="maintenanceLinkVisible"
                                href="/maintenance"
                                class="block px-3 py-2 text-sm text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                            >
                                Maintenance
                            </Link>
                            <Link
                                v-if="posLinkVisible"
                                href="/pos"
                                class="block px-3 py-2 text-sm text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                            >
                                Point de Vente
                            </Link>
                        </div>
                    </div>

                    <div class="relative">
                        <button
                            type="button"
                            class="rounded-full px-3 py-1 text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                            @click="financeOpen = !financeOpen"
                        >
                            Finance ▾
                        </button>
                        <div
                            v-if="financeOpen"
                            class="absolute right-0 z-30 mt-2 w-44 rounded-xl border border-serena-border bg-white shadow-lg"
                        >
                            <Link
                                v-if="cashLinkVisible"
                                href="/cash"
                                class="block px-3 py-2 text-sm text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                            >
                                Caisse
                            </Link>
                            <Link
                                v-if="analyticsLinkVisible"
                                href="/analytics"
                                class="block px-3 py-2 text-sm text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                            >
                                Analytics
                            </Link>
                        </div>
                    </div>

                    <div class="relative">
                        <button
                            type="button"
                            class="relative flex items-center justify-center rounded-full p-2 text-serena-text-muted hover:bg-serena-primary-soft hover:text-serena-primary"
                            @click="notificationsOpen = !notificationsOpen; if (notificationsOpen) loadNotifications();"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V4a2 2 0 10-4 0v1.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0a3 3 0 11-6 0m6 0H9" />
                            </svg>
                            <span
                                v-if="unreadCount > 0"
                                class="absolute -right-1 -top-1 inline-flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-serena-primary px-1 text-[10px] font-semibold text-white"
                            >
                                {{ unreadCount }}
                            </span>
                        </button>

                        <div
                            v-if="notificationsOpen"
                            class="absolute right-0 z-30 mt-2 w-80 rounded-xl border border-serena-border bg-white shadow-lg"
                        >
                            <div class="flex items-center justify-between px-3 py-2">
                                <p class="text-sm font-semibold text-serena-text-main">Notifications</p>
                                <button
                                    type="button"
                                    class="text-xs text-serena-primary hover:underline"
                                    @click="markAllNotificationsRead"
                                >
                                    Tout lire
                                </button>
                            </div>
                            <div v-if="notificationsLoading" class="px-3 py-4 text-sm text-serena-text-muted">Chargement…</div>
                            <div v-else-if="!notifications.length" class="px-3 py-4 text-sm text-serena-text-muted">
                                Aucune notification récente.
                            </div>
                            <ul v-else class="max-h-80 divide-y divide-serena-border overflow-y-auto">
                                <li
                                    v-for="notification in notifications"
                                    :key="notification.id"
                                    class="flex items-start gap-3 px-3 py-2 hover:bg-serena-primary-soft"
                                >
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold text-serena-text-main">
                                            {{ notification.title }}
                                            <span
                                                v-if="!notification.read_at"
                                                class="ml-1 inline-block h-2 w-2 rounded-full bg-serena-primary"
                                            />
                                        </p>
                                        <p class="text-xs text-serena-text-muted">{{ notification.message }}</p>
                                        <p class="text-[11px] text-serena-text-muted">
                                            {{ new Date(notification.created_at).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) }}
                                        </p>
                                    </div>
                                    <div class="flex flex-col items-end gap-1">
                                        <Link
                                            v-if="notification.cta_url"
                                            :href="notification.cta_url"
                                            class="text-xs font-semibold text-serena-primary"
                                        >
                                            Ouvrir
                                        </Link>
                                        <button
                                            type="button"
                                            class="text-[11px] text-serena-text-muted hover:text-serena-primary"
                                            @click="markNotificationRead(notification.id)"
                                        >
                                            Lu
                                        </button>
                                    </div>
                                </li>
                            </ul>
                            <div class="border-t border-serena-border px-3 py-2 text-right">
                                <Link
                                    href="/notifications"
                                    class="text-xs font-semibold text-serena-primary hover:underline"
                                >
                                    Voir tout
                                </Link>
                            </div>
                        </div>
                    </div>

                    <slot name="user-menu">
                        <HeaderUserMenu />
                    </slot>
                </nav>
            </div>
        </header>
        <div
            v-if="mobileNavOpen"
            class="border-b border-serena-border bg-white lg:hidden"
        >
            <div class="page-container space-y-3 py-4 text-sm">
                <div class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">Navigation</p>
                    <div class="flex flex-col gap-2">
                        <Link href="/guests" class="rounded-lg px-3 py-2 text-serena-text-muted hover:bg-serena-primary-soft hover:text-serena-primary" @click="mobileNavOpen = false">
                            Guests
                        </Link>
                        <Link :href="frontdeskDashboard()" class="rounded-lg px-3 py-2 text-serena-text-muted hover:bg-serena-primary-soft hover:text-serena-primary" @click="mobileNavOpen = false">
                            FrontDesk
                        </Link>
                        <Link href="/housekeeping" class="rounded-lg px-3 py-2 text-serena-text-muted hover:bg-serena-primary-soft hover:text-serena-primary" @click="mobileNavOpen = false">
                            Housekeeping
                        </Link>
                        <Link v-if="maintenanceLinkVisible" href="/maintenance" class="rounded-lg px-3 py-2 text-serena-text-muted hover:bg-serena-primary-soft hover:text-serena-primary" @click="mobileNavOpen = false">
                            Maintenance
                        </Link>
                        <Link v-if="posLinkVisible" href="/pos" class="rounded-lg px-3 py-2 text-serena-text-muted hover:bg-serena-primary-soft hover:text-serena-primary" @click="mobileNavOpen = false">
                            Point de Vente
                        </Link>
                        <Link v-if="cashLinkVisible" href="/cash" class="rounded-lg px-3 py-2 text-serena-text-muted hover:bg-serena-primary-soft hover:text-serena-primary" @click="mobileNavOpen = false">
                            Caisse
                        </Link>
                        <Link v-if="analyticsLinkVisible" href="/analytics" class="rounded-lg px-3 py-2 text-serena-text-muted hover:bg-serena-primary-soft hover:text-serena-primary" @click="mobileNavOpen = false">
                            Analytics
                        </Link>
                    </div>
                </div>

                <div class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">Ressources</p>
                    <div class="grid grid-cols-2 gap-2">
                        <Link href="/ressources/hotel" class="rounded-lg px-3 py-2 text-serena-text-muted hover:bg-serena-primary-soft hover:text-serena-primary" @click="mobileNavOpen = false">
                            Hôtel
                        </Link>
                        <Link href="/ressources/room-types" class="rounded-lg px-3 py-2 text-serena-text-muted hover:bg-serena-primary-soft hover:text-serena-primary" @click="mobileNavOpen = false">
                            Types de chambres
                        </Link>
                        <Link href="/ressources/rooms" class="rounded-lg px-3 py-2 text-serena-text-muted hover:bg-serena-primary-soft hover:text-serena-primary" @click="mobileNavOpen = false">
                            Chambres
                        </Link>
                        <Link href="/ressources/offers" class="rounded-lg px-3 py-2 text-serena-text-muted hover:bg-serena-primary-soft hover:text-serena-primary" @click="mobileNavOpen = false">
                            Offres
                        </Link>
                        <Link href="/ressources/taxes" class="rounded-lg px-3 py-2 text-serena-text-muted hover:bg-serena-primary-soft hover:text-serena-primary" @click="mobileNavOpen = false">
                            Taxes
                        </Link>
                        <Link href="/ressources/payment-methods" class="rounded-lg px-3 py-2 text-serena-text-muted hover:bg-serena-primary-soft hover:text-serena-primary" @click="mobileNavOpen = false">
                            Méthodes de paiement
                        </Link>
                        <Link href="/ressources/product-categories" class="rounded-lg px-3 py-2 text-serena-text-muted hover:bg-serena-primary-soft hover:text-serena-primary" @click="mobileNavOpen = false">
                            Catégories de produits
                        </Link>
                        <Link href="/ressources/products" class="rounded-lg px-3 py-2 text-serena-text-muted hover:bg-serena-primary-soft hover:text-serena-primary" @click="mobileNavOpen = false">
                            Produits
                        </Link>
                        <Link href="/ressources/users" class="rounded-lg px-3 py-2 text-serena-text-muted hover:bg-serena-primary-soft hover:text-serena-primary" @click="mobileNavOpen = false">
                            Utilisateurs
                        </Link>
                        <Link href="/activity" class="rounded-lg px-3 py-2 text-serena-text-muted hover:bg-serena-primary-soft hover:text-serena-primary" @click="mobileNavOpen = false">
                            Journal d’activités
                        </Link>
                    </div>
                </div>

                <div v-if="currentUser" class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">Compte</p>
                    <div class="rounded-xl border border-serena-border/60 bg-serena-card p-3 text-sm">
                        <p class="font-semibold text-serena-text-main">{{ currentUser.name }}</p>
                        <p class="text-xs text-serena-text-muted">{{ currentUser.email }}</p>
                        <div v-if="hotels.length" class="mt-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-serena-text-muted">Hôtel actif</p>
                            <div class="mt-2 flex flex-col gap-2">
                                <button
                                    v-for="hotel in hotels"
                                    :key="hotel.id"
                                    type="button"
                                    class="flex items-center justify-between rounded-lg border border-serena-border/60 px-3 py-2 text-left text-serena-text-muted hover:bg-serena-primary-soft hover:text-serena-primary"
                                    @click="switchHotel(hotel.id)"
                                >
                                    <span>{{ hotel.name }}</span>
                                    <span
                                        v-if="activeHotel && activeHotel.id === hotel.id"
                                        class="text-[11px] font-semibold text-serena-primary"
                                    >
                                        Actif
                                    </span>
                                </button>
                            </div>
                        </div>
                        <div class="mt-3 flex flex-col gap-2">
                            <Link
                                :href="edit()"
                                class="rounded-lg border border-serena-border/60 px-3 py-2 text-serena-text-muted hover:bg-serena-primary-soft hover:text-serena-primary"
                                @click="mobileNavOpen = false"
                            >
                                Paramètres
                            </Link>
                            <Link
                                :href="logout()"
                                class="rounded-lg border border-serena-border/60 px-3 py-2 text-serena-text-muted hover:bg-serena-primary-soft hover:text-serena-primary"
                                as="button"
                                @click="handleLogout"
                            >
                                Déconnexion
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <main class="py-6">
            <div class="page-container space-y-4">
                <Breadcrumbs
                    v-if="breadcrumbs.length"
                    :breadcrumbs="breadcrumbs"
                />
                <slot />
            </div>
        </main>

        <div
            v-if="outboxOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4"
        >
            <div class="w-full max-w-xl rounded-2xl bg-white p-5 shadow-xl">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-serena-text-main">File d’attente hors ligne</h3>
                    <button type="button" class="text-sm text-serena-text-muted" @click="outboxOpen = false">
                        Fermer
                    </button>
                </div>
                <div class="mb-3 flex items-center justify-between text-sm">
                    <span>{{ outboxItems.length }} action(s) en attente</span>
                    <button
                        type="button"
                        class="rounded bg-serena-primary px-3 py-1 text-xs font-semibold text-white"
                        @click="syncOutbox"
                    >
                        Synchroniser
                    </button>
                </div>
                <div v-if="!outboxItems.length" class="rounded border border-dashed border-serena-border p-4 text-sm text-serena-text-muted">
                    Aucune action en attente.
                </div>
                <div v-else class="max-h-80 space-y-2 overflow-y-auto">
                    <div
                        v-for="item in outboxItems"
                        :key="item.id"
                        class="rounded border border-serena-border bg-serena-bg-soft p-3 text-sm"
                    >
                        <div class="flex items-center justify-between">
                            <div class="font-semibold text-serena-text-main">{{ item.type }}</div>
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                :class="item.status === 'failed' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'"
                            >
                                {{ item.status }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-serena-text-muted">
                            {{ item.endpoint }} · {{ (item.method || '').toUpperCase() }}
                        </p>
                        <p v-if="item.last_error" class="mt-1 text-xs text-red-600">
                            {{ item.last_error }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
