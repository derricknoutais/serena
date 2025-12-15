<template>
    <ConfigLayout>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-serena-text-main">Analytics</h1>
                <p class="text-sm text-serena-text-muted">Vue synthétique des performances.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <select
                    v-model="selectedPreset"
                    class="rounded-lg border border-serena-border bg-white px-3 py-1.5"
                    @change="applyPreset"
                >
                    <option value="7">7 jours</option>
                    <option value="14">14 jours</option>
                    <option value="30">30 jours</option>
                    <option value="custom">Personnalisé</option>
                </select>
                <input
                    v-if="selectedPreset === 'custom'"
                    v-model="filters.from"
                    type="date"
                    class="rounded-lg border border-serena-border px-3 py-1.5"
                    @change="loadAll"
                />
                <input
                    v-if="selectedPreset === 'custom'"
                    v-model="filters.to"
                    type="date"
                    class="rounded-lg border border-serena-border px-3 py-1.5"
                    @change="loadAll"
                />
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <KpiCard title="Taux d’occupation" :value="formatPercent(summary.occupancy_rate)" hint="Voir détails" link="/frontdesk/dashboard" />
            <KpiCard title="Chambres vendues" :value="summary.rooms_sold" hint="Réservations" link="/reservations" />
            <KpiCard title="Arrivées / Départs" :value="`${summary.arrivals} / ${summary.departures}`" hint="FrontDesk" link="/frontdesk/dashboard" />
            <KpiCard title="CA total" :value="formatCurrency(summary.revenue_total)" hint="Night Audit" link="/night-audit" />
            <KpiCard title="CA Chambres / POS" :value="`${formatCurrency(summary.revenue_rooms)} / ${formatCurrency(summary.revenue_pos)}`" hint="Folio / POS" link="/pos" />
            <KpiCard title="Paiements / Écart caisse" :value="`${formatCurrency(summary.payments_total)} / ${formatCurrency(summary.cash_difference)}`" hint="Caisse" link="/cash" />
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <ChartCard title="Occupation (derniers jours)" :loading="loading.trends">
                <SimpleLine :series="trends.occupancy" color="var(--serena-primary, #2563eb)" />
            </ChartCard>
            <ChartCard title="CA Chambres vs POS" :loading="loading.trends">
                <SimpleStacked
                    :series="[
                        { name: 'Chambres', color: '#2563eb', data: trends.revenue_rooms },
                        { name: 'POS', color: '#f59e0b', data: trends.revenue_pos },
                    ]"
                />
            </ChartCard>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <ChartCard title="Paiements par méthode" :loading="loading.payments">
                <SimpleBars :series="payments.by_method" value-key="total" label-key="payment_method_name" color="#2563eb" />
            </ChartCard>
            <ChartCard title="Écarts de caisse" :loading="loading.payments">
                <SimpleBars :series="payments.cash_sessions" value-key="difference" label-key="type" color="#dc2626" />
            </ChartCard>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <ChartCard title="Top produits" :loading="loading.products">
                <SimpleBars :series="top.products" value-key="revenue" label-key="name" color="#16a34a" />
            </ChartCard>
            <ChartCard title="Top clients (paiements)" :loading="loading.products">
                <SimpleBars :series="top.guests" value-key="total" label-key="name" color="#4b5563" />
            </ChartCard>
        </div>
    </ConfigLayout>
</template>

<script>
import axios from 'axios';
import Swal from 'sweetalert2';
import { h } from 'vue';
import ConfigLayout from '@/layouts/ConfigLayout.vue';

const today = new Date().toISOString().slice(0, 10);
const dateNDaysAgo = (n) => {
    const d = new Date();
    d.setDate(d.getDate() - n + 1);
    return d.toISOString().slice(0, 10);
};

const SimpleLine = {
    name: 'SimpleLine',
    props: ['series', 'color'],
    render() {
        const data = Array.isArray(this.series) ? this.series : [];
        const max = Math.max(...data.map((p) => Number(p.value) || 0), 1);
        const points = data.map((p) => ({
            label: p.date,
            value: Number(p.value) || 0,
            height: `${(Number(p.value) / max) * 100}%`,
        }));

        const bars = points.map((p) =>
            h('div', {
                key: p.label,
                class: 'flex-1 rounded-t-sm',
                style: { height: p.height, backgroundColor: this.color || '#2563eb' },
                title: `${p.label}: ${p.value}`,
            }),
        );

        return h('div', { class: 'space-y-2' }, [
            h('div', { class: 'flex h-36 items-end gap-1' }, bars),
            points.length
                ? h('div', { class: 'mt-2 flex justify-between text-[11px] text-serena-text-muted' }, [
                    h('span', points[0]?.label),
                    h('span', points[points.length - 1]?.label),
                ])
                : null,
        ]);
    },
};

const SimpleStacked = {
    name: 'SimpleStacked',
    props: ['series'],
    render() {
        const series = Array.isArray(this.series) ? this.series : [];
        const labels = series[0]?.data?.map((p) => p.date) || [];
        const points = labels.map((label, idx) => {
            const values = series.map((s) => s.data?.[idx]?.value || 0);
            const total = values.reduce((acc, v) => acc + Number(v || 0), 0);

            return {
                label,
                slices: series.map((s, sIdx) => ({
                    value: Number(values[sIdx] || 0),
                    height: `${(Number(total > 0 ? values[sIdx] : 0) / Math.max(total, 1)) * 100}%`,
                    color: s.color,
                    name: s.name,
                })),
            };
        });

        const bars = points.map((p) =>
            h(
                'div',
                { key: p.label, class: 'flex h-full flex-1 flex-col-reverse overflow-hidden rounded-sm' },
                p.slices.map((slice) =>
                    h('div', {
                        key: slice.name,
                        class: 'w-full',
                        style: { height: slice.height, backgroundColor: slice.color || '#2563eb' },
                        title: `${slice.name}: ${slice.value}`,
                    }),
                ),
            ),
        );

        return h('div', { class: 'space-y-2' }, [
            h('div', { class: 'flex h-36 items-end gap-1' }, bars),
            points.length
                ? h('div', { class: 'mt-2 flex justify-between text-[11px] text-serena-text-muted' }, [
                    h('span', points[0]?.label),
                    h('span', points[points.length - 1]?.label),
                ])
                : null,
        ]);
    },
};

const SimpleBars = {
    name: 'SimpleBars',
    props: ['series', 'valueKey', 'labelKey', 'color'],
    render() {
        const list = Array.isArray(this.series) ? this.series : [];
        const key = this.valueKey || 'value';
        const labelKey = this.labelKey || 'label';
        const max = Math.max(...list.map((s) => Number(s[key] || 0)), 1);

        const items = list.map((item) => {
            const value = Number(item[key] || 0);
            const width = `${(value / max) * 100}%`;

            return h('div', { key: item[labelKey] ?? '' }, [
                h('div', { class: 'flex justify-between text-xs text-serena-text-muted' }, [
                    h('span', item[labelKey] ?? ''),
                    h('span', value.toLocaleString('fr-FR')),
                ]),
                h('div', { class: 'h-2 rounded-full bg-serena-bg-soft' }, [
                    h('div', {
                        class: 'h-2 rounded-full',
                        style: { width, backgroundColor: this.color || '#2563eb' },
                    }),
                ]),
            ]);
        });

        return h('div', { class: 'space-y-2' }, items);
    },
};

const KpiCard = {
    name: 'KpiCard',
    props: ['title', 'value', 'hint', 'link'],
    render() {
        return h('div', { class: 'rounded-xl border border-serena-border bg-white p-4 shadow-sm' }, [
            h('p', { class: 'text-xs font-semibold uppercase tracking-wide text-serena-text-muted' }, this.title),
            h('p', { class: 'mt-2 text-2xl font-bold text-serena-text-main' }, this.value),
            this.link
                ? h(
                      'a',
                      { href: this.link, class: 'mt-2 inline-block text-xs font-semibold text-serena-primary hover:underline' },
                      this.hint || 'Voir détail',
                  )
                : null,
        ]);
    },
};

const ChartCard = {
    name: 'ChartCard',
    props: ['title', 'loading'],
    render() {
        return h('div', { class: 'rounded-xl border border-serena-border bg-white p-4 shadow-sm' }, [
            h('div', { class: 'mb-2 flex items-center justify-between' }, [
                h('p', { class: 'text-sm font-semibold text-serena-text-main' }, this.title),
                this.loading ? h('span', { class: 'text-xs text-serena-text-muted' }, 'Chargement…') : null,
            ]),
            this.$slots.default ? this.$slots.default() : null,
        ]);
    },
};

export default {
    name: 'AnalyticsIndex',
    components: { ConfigLayout, KpiCard, ChartCard, SimpleLine, SimpleStacked, SimpleBars },
    props: {
        defaultHotelId: {
            type: Number,
            default: null,
        },
    },
    data() {
        return {
            filters: {
                hotel_id: this.defaultHotelId,
                from: dateNDaysAgo(7),
                to: today,
            },
            selectedPreset: '7',
            summary: {
                occupancy_rate: 0,
                rooms_sold: 0,
                arrivals: 0,
                departures: 0,
                revenue_total: 0,
                revenue_rooms: 0,
                revenue_pos: 0,
                payments_total: 0,
                cash_difference: 0,
            },
            trends: {
                occupancy: [],
                revenue_rooms: [],
                revenue_pos: [],
            },
            payments: {
                by_method: [],
                cash_sessions: [],
            },
            top: {
                products: [],
                guests: [],
            },
            loading: {
                summary: false,
                trends: false,
                payments: false,
                products: false,
            },
        };
    },
    mounted() {
        this.loadAll();
    },
    methods: {
        applyPreset() {
            const preset = this.selectedPreset;
            if (preset === 'custom') {
                return;
            }
            const days = Number(preset);
            this.filters.from = dateNDaysAgo(days);
            this.filters.to = today;
            this.loadAll();
        },
        async loadAll() {
            await Promise.all([
                this.loadSummary(),
                this.loadTrends(),
                this.loadPayments(),
                this.loadTop(),
            ]);
        },
        async loadSummary() {
            this.loading.summary = true;
            try {
                const { data } = await axios.get('/analytics/summary', { params: this.filters });
                this.summary = data || this.summary;
            } catch (error) {
                this.handleError(error, 'Impossible de charger le résumé.');
            } finally {
                this.loading.summary = false;
            }
        },
        async loadTrends() {
            this.loading.trends = true;
            try {
                const { data } = await axios.get('/analytics/trends', { params: this.filters });
                this.trends = data || this.trends;
            } catch (error) {
                this.handleError(error, 'Impossible de charger les tendances.');
            } finally {
                this.loading.trends = false;
            }
        },
        async loadPayments() {
            this.loading.payments = true;
            try {
                const { data } = await axios.get('/analytics/payments', { params: this.filters });
                this.payments = data || this.payments;
            } catch (error) {
                this.handleError(error, 'Impossible de charger les paiements.');
            } finally {
                this.loading.payments = false;
            }
        },
        async loadTop() {
            this.loading.products = true;
            try {
                const { data } = await axios.get('/analytics/top-products', { params: this.filters });
                this.top = data || this.top;
            } catch (error) {
                this.handleError(error, 'Impossible de charger les tops.');
            } finally {
                this.loading.products = false;
            }
        },
        formatCurrency(value) {
            const amount = Number(value || 0);
            const currency = this.$page?.props?.auth?.user?.currency || 'XAF';

            return `${amount.toLocaleString('fr-FR', { maximumFractionDigits: 0 })} ${currency}`;
        },
        formatPercent(value) {
            const num = Number(value || 0);

            return `${num.toFixed(1)}%`;
        },
        handleError(error, fallback) {
            const message =
                error?.response?.data?.message
                ?? error?.message
                ?? fallback;

            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: message,
            });
        },
    },
};
</script>
