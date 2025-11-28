<template>
    <ConfigLayout>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Modifier un produit</h1>
                <p class="text-sm text-gray-500">Formulaire à implémenter.</p>
            </div>
            <Link href="/ressources/products" class="text-sm text-indigo-600 hover:underline">Retour</Link>
        </div>
        <p class="text-sm text-gray-600">Formulaire à compléter.</p>
    </ConfigLayout>
</template>

<script>
import { Link } from '@inertiajs/vue3';
import ConfigLayout from '@/layouts/ConfigLayout.vue';

export default {
    name: 'ProductsEdit',
    components: { ConfigLayout, Link },
    props: {
        product: {
            type: Object,
            default: null,
        },
        categories: {
            type: Array,
            default: () => [],
        },
        taxes: {
            type: Array,
            default: () => [],
        },
    },
};
</script>
