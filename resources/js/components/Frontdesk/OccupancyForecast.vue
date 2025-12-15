<template>
    <div class="rounded-2xl bg-white p-4 shadow-sm">
        <div class="mb-4 flex flex-wrap items-center gap-3 justify-between">
            <div>
                <h2 class="text-lg font-semibold text-serena-text-main">Prévision d’occupation</h2>
                <p class="text-sm text-serena-text-muted">Chambres au total : {{ forecast?.total_rooms ?? 0 }}</p>
            </div>
            <div class="flex items-center gap-2">
                <button
                    v-for="option in options"
                    :key="option.value"
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-semibold transition"
                    :class="days === option.value ? 'bg-serena-primary text-white' : 'bg-gray-100 text-serena-text-muted hover:bg-gray-200'"
                    @click="setDays(option.value)"
                >
                    {{ option.label }}
                </button>
            </div>
        </div>

        <div v-if="!forecast" class="rounded border border-dashed border-serena-border p-4 text-sm text-serena-text-muted">
            Aucune donnée pour le moment.
        </div>

        <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-serena-border text-sm">
                <thead class="bg-serena-bg-soft/70 text-left text-xs font-semibold uppercase tracking-wide text-serena-text-muted">
                    <tr>
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2">Chambres vendues</th>
                        <th class="px-3 py-2">Arrivées</th>
                        <th class="px-3 py-2">Départs</th>
                        <th class="px-3 py-2">Taux</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-serena-border/60 text-serena-text-main">
                    <tr v-for="row in forecast.rows" :key="row.date">
                        <td class="px-3 py-2">{{ row.date }}</td>
                        <td class="px-3 py-2">{{ row.sold_rooms }}</td>
                        <td class="px-3 py-2">{{ row.arrivals }}</td>
                        <td class="px-3 py-2">{{ row.departures }}</td>
                        <td class="px-3 py-2">
                            <div class="mb-1 flex items-center gap-2">
                                <span class="text-sm font-semibold">{{ row.occupancy_rate }}%</span>
                                <div class="relative h-2 w-32 overflow-hidden rounded-full bg-gray-100">
                                    <div
                                        class="absolute left-0 top-0 h-2 rounded-full bg-serena-primary"
                                        :style="{ width: `${Math.min(row.occupancy_rate, 100)}%` }"
                                    />
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import Swal from 'sweetalert2';

export default {
    name: 'OccupancyForecast',
    props: {
        initialForecast: {
            type: Object,
            default: null,
        },
        canView: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {
            forecast: this.initialForecast,
            days: 7,
            options: [
                { value: 7, label: '7 jours' },
                { value: 14, label: '14 jours' },
            ],
        };
    },
    methods: {
        setDays(value) {
            this.days = value;
            this.fetchForecast();
        },
        async fetchForecast() {
            if (!this.canView) {
                Swal.fire({
                    icon: 'error',
                    title: 'Non autorisé',
                    text: 'Vous ne pouvez pas consulter la prévision.',
                });
                return;
            }

            try {
                const response = await axios.get('/frontdesk/forecast', {
                    params: {
                        days: this.days,
                    },
                    headers: { Accept: 'application/json' },
                });

                this.forecast = response.data;
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Impossible de charger la prévision.',
                });
            }
        },
    },
};
</script>
