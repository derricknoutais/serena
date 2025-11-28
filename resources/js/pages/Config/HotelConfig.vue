<template>
    <ConfigLayout>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Informations de l’hôtel</h1>
                <p class="text-sm text-gray-500">Mettre à jour les informations générales de l’établissement.</p>
            </div>
            <div v-if="flashMessage" class="rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700">
                {{ flashMessage }}
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm font-medium text-gray-700">Nom</label>
                    <input v-model="form.name" type="text" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100" />
                    <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Code</label>
                    <input v-model="form.code" type="text" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100" />
                    <p v-if="errors.code" class="mt-1 text-xs text-red-600">{{ errors.code }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Devise</label>
                    <input v-model="form.currency" type="text" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm uppercase focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100" />
                    <p v-if="errors.currency" class="mt-1 text-xs text-red-600">{{ errors.currency }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Fuseau horaire</label>
                    <input v-model="form.timezone" type="text" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100" />
                    <p v-if="errors.timezone" class="mt-1 text-xs text-red-600">{{ errors.timezone }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Heure d’arrivée (check-in)</label>
                    <input v-model="form.check_in_time" type="time" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100" />
                    <p v-if="errors.check_in_time" class="mt-1 text-xs text-red-600">{{ errors.check_in_time }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Heure de départ (check-out)</label>
                    <input v-model="form.check_out_time" type="time" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100" />
                    <p v-if="errors.check_out_time" class="mt-1 text-xs text-red-600">{{ errors.check_out_time }}</p>
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm font-medium text-gray-700">Adresse</label>
                    <input v-model="form.address" type="text" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100" />
                    <p v-if="errors.address" class="mt-1 text-xs text-red-600">{{ errors.address }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Ville</label>
                    <input v-model="form.city" type="text" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100" />
                    <p v-if="errors.city" class="mt-1 text-xs text-red-600">{{ errors.city }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Pays</label>
                    <input v-model="form.country" type="text" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100" />
                    <p v-if="errors.country" class="mt-1 text-xs text-red-600">{{ errors.country }}</p>
                </div>
            </div>

            <div class="flex justify-end">
                <button
                    type="submit"
                    class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="processing"
                >
                    <span v-if="processing">Enregistrement…</span>
                    <span v-else>Enregistrer</span>
                </button>
            </div>
        </form>
    </ConfigLayout>
</template>

<script>
import { router } from '@inertiajs/vue3';
import ConfigLayout from '@/layouts/ConfigLayout.vue';

export default {
    name: 'HotelConfigPage',
    components: { ConfigLayout },
    props: {
        hotel: {
            type: Object,
            required: true,
        },
        flash: {
            type: Object,
            default: () => ({}),
        },
    },
    data() {
        return {
            form: {
                name: this.hotel.name || '',
                code: this.hotel.code || '',
                currency: this.hotel.currency || '',
                timezone: this.hotel.timezone || '',
                check_in_time: this.hotel.check_in_time || '',
                check_out_time: this.hotel.check_out_time || '',
                address: this.hotel.address || '',
                city: this.hotel.city || '',
                country: this.hotel.country || '',
            },
            processing: false,
        };
    },
    computed: {
        errors() {
            return this.$page.props.errors || {};
        },
        flashMessage() {
            return this.flash?.success;
        },
    },
    methods: {
        submit() {
            this.processing = true;
            router.put('/ressources/hotel', this.form, {
                onFinish: () => {
                    this.processing = false;
                },
            });
        },
    },
};
</script>
