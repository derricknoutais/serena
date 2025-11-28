<template>
    <ConfigLayout>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Produits (Bar / Restau)</h1>
                <p class="text-sm text-gray-500">Gestion des articles vendus.</p>
            </div>
            <Link
                href="/ressources/products/create"
                class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
            >
                Nouveau produit
            </Link>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Nom</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Catégorie</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Prix</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Compte</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Actif</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="product in products.data" :key="product.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-800">{{ product.name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ product.category || '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ product.unit_price }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ product.account_code }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <span
                                class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                :class="product.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'"
                            >
                                {{ product.is_active ? 'Oui' : 'Non' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 space-x-3">
                            <Link :href="`/ressources/products/${product.id}/edit`" class="text-indigo-600 hover:underline">Éditer</Link>
                            <button type="button" class="text-red-600 hover:underline" @click="destroy(product.id)">Supprimer</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="border-t border-gray-100 px-4 py-3 text-sm text-gray-500">
                Pagination à ajouter selon vos besoins.
            </div>
        </div>
    </ConfigLayout>
</template>

<script>
import { Link, router } from '@inertiajs/vue3';
import ConfigLayout from '@/layouts/ConfigLayout.vue';

export default {
    name: 'ProductsIndex',
    components: { ConfigLayout, Link },
    props: {
        products: {
            type: Object,
            required: true,
        },
    },
    methods: {
        destroy(id) {
            if (confirm('Supprimer ce produit ?')) {
                router.delete(`/ressources/products/${id}`);
            }
        },
    },
};
</script>
