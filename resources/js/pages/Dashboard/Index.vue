<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <div class="rounded-3xl border border-serena-border bg-gradient-to-br from-serena-primary-soft/70 via-white to-white p-6 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase text-serena-primary">Dashboard</p>
                        <h1 class="text-2xl font-bold text-serena-text-main">Synthèse opérationnelle</h1>
                        <p class="text-sm text-serena-text-muted">
                            Vue rapide des actions prioritaires et des chiffres clés.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-serena-text-muted shadow-sm">
                            Mis à jour en direct
                        </span>
                    </div>
                </div>
            </div>

            <div v-if="canViewAllHotels" class="rounded-2xl border border-serena-border bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-2 text-sm font-semibold text-serena-text-main">
                        <input v-model="localFilters.all_hotels" type="checkbox" class="h-4 w-4 rounded border-serena-border text-serena-primary focus:ring-serena-primary" />
                        Tous les hôtels
                    </label>
                    <select
                        v-model="localFilters.hotel_id"
                        class="rounded-xl border border-serena-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-serena-primary/40"
                        :disabled="localFilters.all_hotels"
                    >
                        <option value="">Hôtel actif</option>
                        <option v-for="hotel in hotels" :key="hotel.id" :value="hotel.id">
                            {{ hotel.name }}
                        </option>
                    </select>
                    <PrimaryButton type="button" class="px-3 py-2 text-xs" @click="applyFilters">
                        Appliquer
                    </PrimaryButton>
                </div>
            </div>

            <div v-if="!widgets.length" class="rounded-2xl border border-dashed border-serena-border bg-white p-8 text-center text-sm text-serena-text-muted">
                Aucun widget disponible pour votre rôle.
            </div>

            <div v-else class="space-y-6">
                <div class="grid gap-4 lg:grid-cols-3">
                    <div
                        v-for="(widget, index) in featuredWidgets"
                        :key="widget.key"
                        class="rounded-3xl border border-serena-border p-5 shadow-sm"
                        :class="accentCard(index)"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase text-serena-text-muted">Priorité</p>
                                <p class="text-lg font-semibold text-serena-text-main">{{ widget.title }}</p>
                            </div>
                            <span class="rounded-full bg-white/80 px-2 py-1 text-[11px] font-semibold text-serena-text-muted">
                                {{ widget.stats.length }} indicateurs
                            </span>
                        </div>
                        <div class="mt-5 flex items-end justify-between gap-4">
                            <div>
                                <p class="text-xs uppercase text-serena-text-muted">{{ primaryStat(widget).label }}</p>
                                <p class="text-3xl font-semibold text-serena-text-main">
                                    {{ primaryStat(widget).value }}
                                </p>
                            </div>
                            <div class="flex h-16 w-32 items-end gap-1">
                                <div
                                    v-for="(bar, barIndex) in widgetSeries(widget)"
                                    :key="`${widget.key}-bar-${barIndex}`"
                                    class="flex-1 rounded-t-md"
                                    :class="accentBar(index)"
                                    :style="{ height: bar.height }"
                                ></div>
                            </div>
                        </div>
                        <div class="mt-4 grid gap-2 text-xs text-serena-text-muted sm:grid-cols-2">
                            <div v-for="stat in secondaryStats(widget)" :key="stat.label" class="flex items-center justify-between">
                                <span>{{ stat.label }}</span>
                                <span class="font-semibold text-serena-text-main">{{ stat.value }}</span>
                            </div>
                        </div>
                        <div v-if="widget.actions && widget.actions.length" class="mt-4 flex flex-wrap gap-2">
                            <Link
                                v-for="action in widget.actions"
                                :key="action.href"
                                :href="action.href"
                                class="rounded-full border border-serena-border bg-white/70 px-3 py-1 text-xs font-semibold text-serena-text-muted transition hover:border-serena-primary hover:text-serena-primary"
                            >
                                {{ action.label }}
                            </Link>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-3">
                    <div class="rounded-3xl border border-serena-border bg-white p-5 shadow-sm lg:col-span-2">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase text-serena-primary">Tendances</p>
                                <p class="text-lg font-semibold text-serena-text-main">Vue synthétique</p>
                            </div>
                            <span class="text-xs text-serena-text-muted">7 derniers points</span>
                        </div>
                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <div class="space-y-3">
                                <p class="text-xs font-semibold uppercase text-serena-text-muted">Répartition rapide</p>
                                <div class="space-y-3">
                                    <div v-for="(item, idx) in summarySeries" :key="`summary-${idx}`" class="space-y-1">
                                        <div class="flex items-center justify-between text-xs text-serena-text-muted">
                                            <span>{{ item.label }}</span>
                                            <span class="font-semibold text-serena-text-main">{{ item.value }}</span>
                                        </div>
                                        <div class="h-2 rounded-full bg-serena-bg-soft">
                                            <div class="h-2 rounded-full bg-serena-primary" :style="{ width: item.width }"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-3 rounded-2xl bg-serena-primary-soft/40 p-4">
                                <p class="text-xs font-semibold uppercase text-serena-text-muted">Points chauds</p>
                                <div class="space-y-2">
                                    <div v-for="(item, idx) in priorityWidgets" :key="`priority-${idx}`" class="flex items-center justify-between text-sm">
                                        <span class="text-serena-text-main">{{ item.title }}</span>
                                        <span class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-serena-primary">
                                            {{ item.primaryValue }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-3xl border border-serena-border bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase text-serena-primary">À surveiller</p>
                                <p class="text-lg font-semibold text-serena-text-main">Actions prioritaires</p>
                            </div>
                        </div>
                        <div class="mt-5 space-y-3">
                            <div v-for="(item, idx) in topStats" :key="`top-stat-${idx}`" class="rounded-2xl border border-serena-border/70 bg-serena-primary-soft/30 p-3">
                                <p class="text-xs font-semibold uppercase text-serena-text-muted">{{ item.label }}</p>
                                <p class="text-xl font-semibold text-serena-text-main">{{ item.value }}</p>
                                <p class="text-xs text-serena-text-muted">{{ item.parent }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="secondaryWidgets.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div
                        v-for="widget in secondaryWidgets"
                        :key="widget.key"
                        class="rounded-2xl border border-serena-border bg-white p-4 shadow-sm"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm font-semibold text-serena-text-main">{{ widget.title }}</p>
                            </div>
                        </div>
                        <div class="mt-4 space-y-2">
                            <div
                                v-for="stat in widget.stats.slice(0, 4)"
                                :key="stat.label"
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="text-serena-text-muted">{{ stat.label }}</span>
                                <span class="font-semibold text-serena-text-main">{{ stat.value }}</span>
                            </div>
                        </div>
                        <div v-if="widget.actions && widget.actions.length" class="mt-4 flex flex-wrap gap-2">
                            <Link
                                v-for="action in widget.actions"
                                :key="action.href"
                                :href="action.href"
                                class="rounded-full border border-serena-border px-3 py-1 text-xs font-semibold text-serena-text-muted transition hover:border-serena-primary hover:text-serena-primary"
                            >
                                {{ action.label }}
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';

export default {
    name: 'DashboardIndex',
    components: {
        AppLayout,
        Link,
        PrimaryButton,
    },
    props: {
        widgets: {
            type: Array,
            default: () => [],
        },
        filters: {
            type: Object,
            default: () => ({}),
        },
        canViewAllHotels: {
            type: Boolean,
            default: false,
        },
        hotels: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            localFilters: { ...this.filters },
        };
    },
    computed: {
        breadcrumbs() {
            return [
                { title: 'Dashboard', href: '/dashboard' },
            ];
        },
        isOwner() {
            const roles = this.$page?.props?.auth?.user?.roles || [];
            return roles.some((role) => role.name === 'owner');
        },
        isManager() {
            const roles = this.$page?.props?.auth?.user?.roles || [];
            return roles.some((role) => role.name === 'manager');
        },
        featuredWidgets() {
            return this.widgets.slice(0, 3);
        },
        secondaryWidgets() {
            const maxSecondary = this.isOwner ? 2 : this.isManager ? 3 : 6;
            return this.widgets.slice(3, 3 + maxSecondary);
        },
        summarySeries() {
            const stats = this.widgets.flatMap((widget) =>
                widget.stats.map((stat) => ({
                    label: stat.label,
                    value: this.parseNumeric(stat.value),
                })),
            );
            const top = stats.sort((a, b) => b.value - a.value).slice(0, 6);
            const max = Math.max(...top.map((item) => item.value), 1);

            return top.map((item) => ({
                ...item,
                value: item.value.toLocaleString('fr-FR'),
                width: `${(item.value / max) * 100}%`,
            }));
        },
        priorityWidgets() {
            return this.widgets
                .map((widget) => {
                    const primary = this.primaryStat(widget);
                    return {
                        title: widget.title,
                        primaryValue: primary.value,
                        numeric: this.parseNumeric(primary.value),
                    };
                })
                .sort((a, b) => b.numeric - a.numeric)
                .slice(0, 4);
        },
        topStats() {
            const stats = this.widgets.flatMap((widget) =>
                widget.stats.map((stat) => ({
                    label: stat.label,
                    value: stat.value,
                    numeric: this.parseNumeric(stat.value),
                    parent: widget.title,
                })),
            );

            return stats
                .sort((a, b) => b.numeric - a.numeric)
                .slice(0, 3);
        },
    },
    methods: {
        applyFilters() {
            router.get('/dashboard', this.localFilters, { preserveState: true, replace: true });
        },
        parseNumeric(value) {
            if (typeof value === 'number') {
                return value;
            }
            const cleaned = String(value ?? '')
                .replace(/[^0-9,.-]/g, '')
                .replace(',', '.');
            const parsed = Number(cleaned);

            return Number.isFinite(parsed) ? parsed : 0;
        },
        primaryStat(widget) {
            return widget.stats[0] ?? { label: 'Total', value: 0 };
        },
        secondaryStats(widget) {
            return widget.stats.slice(1, 3);
        },
        widgetSeries(widget) {
            const values = widget.stats.map((stat) => this.parseNumeric(stat.value));
            const series = values.length ? values.slice(0, 6) : [0, 0, 0, 0, 0, 0];
            const max = Math.max(...series, 1);

            return series.map((value) => ({
                height: `${Math.max((value / max) * 100, 12)}%`,
            }));
        },
        accentCard(index) {
            const accents = [
                'bg-serena-primary-soft/60 border-serena-primary/30',
                'bg-amber-50 border-amber-200/60',
                'bg-emerald-50 border-emerald-200/60',
            ];
            return accents[index % accents.length];
        },
        accentBar(index) {
            const accents = [
                'bg-serena-primary',
                'bg-amber-500',
                'bg-emerald-500',
            ];
            return accents[index % accents.length];
        },
    },
};
</script>
