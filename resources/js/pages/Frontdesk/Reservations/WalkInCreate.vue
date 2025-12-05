<template>
    <AppLayout>
        <div class="mx-auto max-w-3xl">
            <div class="mb-6">
                <h1 class="text-xl font-semibold text-serena-text-main">
                    Walk-In Reservation
                </h1>
                <p class="text-sm text-serena-text-muted">
                    Créez une réservation immédiate pour la chambre sélectionnée.
                </p>
            </div>

            <div
                class="space-y-6 rounded-xl border border-serena-border/30 bg-serena-card p-6 shadow-sm"
            >
                <section>
                    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-serena-text-muted">
                        Chambre
                    </h2>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <p class="text-xs font-medium text-serena-text-muted">
                                Numéro
                            </p>
                            <p class="text-sm font-semibold text-serena-text-main">
                                {{ room.number }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-serena-text-muted">
                                Type
                            </p>
                            <p class="text-sm text-serena-text-main">
                                {{ room.room_type_name }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-serena-text-muted">
                                Capacité
                            </p>
                            <p class="text-sm text-serena-text-main">
                                {{ roomType.capacity_adults }} adultes,
                                {{ roomType.capacity_children }} enfants
                            </p>
                        </div>
                    </div>
                </section>

                <form @submit.prevent="submit" class="space-y-6">
                    <section class="space-y-4">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-serena-text-muted">
                            Client
                        </h2>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label
                                    for="guest_id"
                                    class="mb-1 block text-xs font-medium text-serena-text-muted"
                                >
                                    ID client (optionnel)
                                </label>
                                <input
                                    id="guest_id"
                                    v-model="form.guest_id"
                                    type="number"
                                    min="1"
                                    class="w-full rounded-lg border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                                />
                                <p
                                    v-if="errors.guest_id"
                                    class="mt-1 text-xs text-serena-danger"
                                >
                                    {{ errors.guest_id }}
                                </p>
                            </div>
                            <div />
                            <div>
                                <label
                                    for="guest_first_name"
                                    class="mb-1 block text-xs font-medium text-serena-text-muted"
                                >
                                    Prénom
                                </label>
                                <input
                                    id="guest_first_name"
                                    v-model="form.guest_first_name"
                                    type="text"
                                    class="w-full rounded-lg border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                                />
                                <p
                                    v-if="errors.guest_first_name"
                                    class="mt-1 text-xs text-serena-danger"
                                >
                                    {{ errors.guest_first_name }}
                                </p>
                            </div>
                            <div>
                                <label
                                    for="guest_last_name"
                                    class="mb-1 block text-xs font-medium text-serena-text-muted"
                                >
                                    Nom
                                </label>
                                <input
                                    id="guest_last_name"
                                    v-model="form.guest_last_name"
                                    type="text"
                                    class="w-full rounded-lg border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                                />
                                <p
                                    v-if="errors.guest_last_name"
                                    class="mt-1 text-xs text-serena-danger"
                                >
                                    {{ errors.guest_last_name }}
                                </p>
                            </div>
                            <div>
                                <label
                                    for="guest_phone"
                                    class="mb-1 block text-xs font-medium text-serena-text-muted"
                                >
                                    Téléphone
                                </label>
                                <input
                                    id="guest_phone"
                                    v-model="form.guest_phone"
                                    type="text"
                                    class="w-full rounded-lg border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                                />
                                <p
                                    v-if="errors.guest_phone"
                                    class="mt-1 text-xs text-serena-danger"
                                >
                                    {{ errors.guest_phone }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <section class="space-y-4">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-serena-text-muted">
                            Séjour
                        </h2>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label
                                    for="check_in_date"
                                    class="mb-1 block text-xs font-medium text-serena-text-muted"
                                >
                                    Arrivée
                                </label>
                                <input
                                    id="check_in_date"
                                    v-model="form.check_in_date"
                                    type="date"
                                    class="w-full rounded-lg border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                                />
                                <p
                                    v-if="errors.check_in_date"
                                    class="mt-1 text-xs text-serena-danger"
                                >
                                    {{ errors.check_in_date }}
                                </p>
                            </div>
                            <div>
                                <label
                                    for="check_out_date"
                                    class="mb-1 block text-xs font-medium text-serena-text-muted"
                                >
                                    Départ
                                </label>
                                <input
                                    id="check_out_date"
                                    v-model="form.check_out_date"
                                    type="date"
                                    class="w-full rounded-lg border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                                />
                                <p
                                    v-if="errors.check_out_date"
                                    class="mt-1 text-xs text-serena-danger"
                                >
                                    {{ errors.check_out_date }}
                                </p>
                            </div>
                            <div>
                                <label
                                    for="offer_id"
                                    class="mb-1 block text-xs font-medium text-serena-text-muted"
                                >
                                    Offre
                                </label>
                                <select
                                    id="offer_id"
                                    v-model.number="form.offer_id"
                                    @change="onOfferChange"
                                    class="w-full rounded-lg border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                                >
                                    <option
                                        v-for="offer in offers"
                                        :key="offer.id"
                                        :value="offer.id"
                                    >
                                        {{ offer.name }} — {{ offer.price }}
                                    </option>
                                </select>
                                <p
                                    v-if="errors.offer_id"
                                    class="mt-1 text-xs text-serena-danger"
                                >
                                    {{ errors.offer_id }}
                                </p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label
                                        for="adults"
                                        class="mb-1 block text-xs font-medium text-serena-text-muted"
                                    >
                                        Adultes
                                    </label>
                                    <input
                                        id="adults"
                                        v-model.number="form.adults"
                                        type="number"
                                        min="1"
                                        class="w-full rounded-lg border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                                    />
                                    <p
                                        v-if="errors.adults"
                                        class="mt-1 text-xs text-serena-danger"
                                    >
                                        {{ errors.adults }}
                                    </p>
                                </div>
                                <div>
                                    <label
                                        for="children"
                                        class="mb-1 block text-xs font-medium text-serena-text-muted"
                                    >
                                        Enfants
                                    </label>
                                    <input
                                        id="children"
                                        v-model.number="form.children"
                                        type="number"
                                        min="0"
                                        class="w-full rounded-lg border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                                    />
                                    <p
                                        v-if="errors.children"
                                        class="mt-1 text-xs text-serena-danger"
                                    >
                                        {{ errors.children }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="flex justify-end">
                        <PrimaryButton
                            type="submit"
                            class="px-6 py-2 text-sm"
                            :disabled="form.processing"
                        >
                            Confirmer le Walk-In
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';

export default {
    name: 'WalkInCreate',
    components: {
        AppLayout,
        PrimaryButton,
    },
    props: {
        room: {
            type: Object,
            required: true,
        },
        roomType: {
            type: Object,
            required: true,
        },
        defaultDates: {
            type: Object,
            required: true,
        },
        offers: {
            type: Array,
            required: true,
        },
        source: {
            type: String,
            default: 'walk_in',
        },
        errors: {
            type: Object,
            default: () => ({}),
        },
    },
    data() {
        const initialOffer = this.offers.length ? this.offers[0] : null;

        return {
            form: useForm({
                guest_id: null,
                guest_first_name: '',
                guest_last_name: '',
                guest_phone: '',
                room_id: this.room.id,
                room_type_id: this.room.room_type_id,
                offer_id: initialOffer ? initialOffer.id : null,
                offer_price_id: initialOffer ? initialOffer.offer_price_id : null,
                check_in_date: this.defaultDates.check_in_date,
                check_out_date: this.defaultDates.check_out_date,
                adults: 1,
                children: 0,
            }),
        };
    },
    methods: {
        onOfferChange() {
            const selected = this.offers.find(
                (offer) => offer.id === this.form.offer_id,
            );
            if (selected) {
                this.form.offer_price_id = selected.offer_price_id;
            }
        },
        submit() {
            this.form.post('/reservations/walk-in');
        },
    },
};
</script>

