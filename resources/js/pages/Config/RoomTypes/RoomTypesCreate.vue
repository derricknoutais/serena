<template>
    <ConfigLayout>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Nouveau type de chambre</h1>
                <p class="text-sm text-gray-500">Créer une nouvelle catégorie.</p>
            </div>
            <Link href="/ressources/room-types" class="text-sm text-indigo-600 hover:underline">Retour</Link>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm font-medium text-gray-700">Nom</label>
                    <input v-model="form.name" type="text" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100" />
                    <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Capacité adultes</label>
                    <input v-model.number="form.capacity_adults" type="number" min="1" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100" />
                    <p v-if="errors.capacity_adults" class="mt-1 text-xs text-red-600">{{ errors.capacity_adults }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Capacité enfants</label>
                    <input v-model.number="form.capacity_children" type="number" min="0" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100" />
                    <p v-if="errors.capacity_children" class="mt-1 text-xs text-red-600">{{ errors.capacity_children }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Tarif de base</label>
                    <input v-model.number="form.base_price" type="number" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100" />
                    <p v-if="errors.base_price" class="mt-1 text-xs text-red-600">{{ errors.base_price }}</p>
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm font-medium text-gray-700">Description</label>
                    <textarea v-model="form.description" rows="3" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"></textarea>
                    <p v-if="errors.description" class="mt-1 text-xs text-red-600">{{ errors.description }}</p>
                </div>
            </div>

            <div class="flex justify-end">
                <PrimaryButton type="submit" class="px-4 py-2 text-sm" :disabled="processing">
                    <span v-if="processing">Enregistrement…</span>
                    <span v-else>Enregistrer</span>
                </PrimaryButton>
            </div>
        </form>
    </ConfigLayout>
</template>

<script>
import { Link, router } from '@inertiajs/vue3';
import ConfigLayout from '@/layouts/ConfigLayout.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';

export default {
    name: 'RoomTypesCreate',
    components: { ConfigLayout, Link, PrimaryButton },
    data() {
        return {
            form: {
                name: '',
                capacity_adults: 1,
                capacity_children: 0,
                base_price: 0,
                description: '',
            },
            processing: false,
        };
    },
    computed: {
        errors() {
            return this.$page.props.errors || {};
        },
    },
    methods: {
        submit() {
            this.processing = true;
            router.post('/ressources/room-types', this.form, {
                onFinish: () => {
                    this.processing = false;
                },
            });
        },
    },
};
</script>
