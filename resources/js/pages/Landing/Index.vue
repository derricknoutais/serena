<template>
    <MarketingLayout :can-register="canRegister">
        <Head title="Serena — Gestion hôtelière repensée" />

        <div class="bg-white">
            <section class="relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-[#25b0eb]/10 via-white to-white"></div>
                <div class="page-container relative grid gap-10 py-16 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
                    <div class="space-y-6">
                        <span class="inline-flex items-center gap-2 rounded-full bg-[#25b0eb]/10 px-4 py-1 text-xs font-semibold uppercase tracking-wide text-[#25b0eb]">
                            Serena PMS
                        </span>
                        <h1 class="text-4xl font-semibold leading-tight text-serena-text-main sm:text-5xl">
                            Un PMS moderne pour des hôtels qui veulent aller plus vite.
                        </h1>
                        <p class="text-lg text-serena-text-muted">
                            Centralisez le FrontDesk, les chambres, la facturation et les opérations quotidiennes.
                            Moins de frictions, plus de contrôle, une équipe qui travaille sur la même source.
                        </p>
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-full bg-[#25b0eb] px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:brightness-105"
                                @click="scrollToDemo"
                            >
                                Demander une démo
                            </button>
                            <Link
                                href="/login"
                                class="inline-flex items-center justify-center rounded-full border border-serena-border bg-white px-6 py-3 text-sm font-semibold text-serena-text-main transition hover:bg-serena-primary-soft"
                            >
                                Se connecter
                            </Link>
                        </div>
                        <div class="flex flex-wrap gap-4 text-xs text-serena-text-muted">
                            <span>✓ Déploiement rapide</span>
                            <span>✓ Multi-hôtels</span>
                            <span>✓ Support local</span>
                        </div>
                    </div>
                    <div class="relative">
                        <div class="absolute -right-8 top-10 h-48 w-48 rounded-full bg-[#25b0eb]/10 blur-3xl"></div>
                        <div class="rounded-3xl border border-serena-border bg-white p-6 shadow-xl">
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold text-serena-text-muted">Arrivées aujourd’hui</span>
                                    <span class="text-3xl font-semibold text-serena-text-main">24</span>
                                </div>
                                <div class="h-2 rounded-full bg-serena-bg-soft">
                                    <div class="h-2 w-3/4 rounded-full bg-[#25b0eb]"></div>
                                </div>
                                <div class="grid gap-3 rounded-2xl bg-serena-primary-soft/40 p-4 text-sm">
                                    <div class="flex items-center justify-between">
                                        <span class="text-serena-text-muted">Clients sur place</span>
                                        <span class="font-semibold text-serena-text-main">68</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-serena-text-muted">Chambres prêtes</span>
                                        <span class="font-semibold text-serena-text-main">42</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-serena-text-muted">Alertes critiques</span>
                                        <span class="font-semibold text-serena-accent">3</span>
                                    </div>
                                </div>
                                <p class="text-xs text-serena-text-muted">
                                    Serena donne une vision claire et immédiate des opérations.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-serena-bg-soft py-14">
                <div class="page-container grid gap-10 lg:grid-cols-2">
                    <div class="space-y-4">
                        <h2 class="text-2xl font-semibold text-serena-text-main">Le problème</h2>
                        <ul class="space-y-3 text-sm text-serena-text-muted">
                            <li>• Équipes qui jonglent entre trop d’outils et de feuilles Excel.</li>
                            <li>• Perte de temps sur des tâches répétitives et des validations manuelles.</li>
                            <li>• Visibilité limitée sur les chambres, la caisse et les incidents.</li>
                        </ul>
                    </div>
                    <div class="space-y-4">
                        <h2 class="text-2xl font-semibold text-serena-text-main">La solution</h2>
                        <p class="text-sm text-serena-text-muted">
                            Serena centralise tout le quotidien d’un hôtel : frontdesk, room board, facturation,
                            housekeeping, bar/POS et historique. Chaque action est tracée et disponible en temps réel.
                        </p>
                        <div class="grid gap-2 text-sm text-serena-text-muted sm:grid-cols-2">
                            <span>• FrontDesk & RoomBoard</span>
                            <span>• Facturation & paiements</span>
                            <span>• Housekeeping & maintenance</span>
                            <span>• Bar/POS & historique</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="py-16">
                <div class="page-container space-y-10">
                    <div class="max-w-2xl space-y-3">
                        <h2 class="text-2xl font-semibold text-serena-text-main">Trois bénéfices immédiats</h2>
                        <p class="text-sm text-serena-text-muted">
                            Serena réduit les erreurs, accélère les opérations et donne une vue claire aux managers.
                        </p>
                    </div>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-2xl border border-serena-border bg-white p-5 shadow-sm">
                            <p class="text-sm font-semibold text-serena-text-main">Moins d’erreurs</p>
                            <p class="mt-2 text-sm text-serena-text-muted">
                                Des process guidés et des validations automatiques pour éviter les oublis.
                            </p>
                        </div>
                        <div class="rounded-2xl border border-serena-border bg-white p-5 shadow-sm">
                            <p class="text-sm font-semibold text-serena-text-main">Plus de contrôle</p>
                            <p class="mt-2 text-sm text-serena-text-muted">
                                Un suivi précis des actions, des paiements et des interventions.
                            </p>
                        </div>
                        <div class="rounded-2xl border border-serena-border bg-white p-5 shadow-sm">
                            <p class="text-sm font-semibold text-serena-text-main">Gain de temps</p>
                            <p class="mt-2 text-sm text-serena-text-muted">
                                Des tableaux clairs pour que les équipes se concentrent sur l’accueil.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-serena-bg-soft py-16">
                <div class="page-container space-y-8">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <h2 class="text-2xl font-semibold text-serena-text-main">Captures produit</h2>
                            <p class="text-sm text-serena-text-muted">
                                Remplacez ces visuels par vos captures quand elles sont prêtes.
                            </p>
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div
                            v-for="(shot, idx) in screenshots"
                            :key="`${shot.title}-${idx}`"
                            class="group relative overflow-hidden rounded-2xl border border-serena-border bg-white shadow-sm"
                        >
                            <div class="h-52 bg-gradient-to-br from-[#25b0eb]/20 via-white to-white"></div>
                            <div class="space-y-1 p-4">
                                <p class="text-sm font-semibold text-serena-text-main">{{ shot.title }}</p>
                                <p class="text-xs text-serena-text-muted">{{ shot.description }}</p>
                            </div>
                            <span class="absolute right-4 top-4 rounded-full bg-white/80 px-3 py-1 text-[11px] font-semibold text-serena-text-muted">
                                Aperçu
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="py-16">
                <div class="page-container grid gap-8 lg:grid-cols-[1.1fr_0.9fr] lg:items-start">
                    <div class="space-y-4">
                        <h2 class="text-2xl font-semibold text-serena-text-main">Un prix simple</h2>
                        <p class="text-sm text-serena-text-muted">
                            Une seule offre pour un PMS complet, sans frais cachés ni modules bloquants.
                        </p>
                        <ul class="space-y-2 text-sm text-serena-text-muted">
                            <li>• FrontDesk, RoomBoard, Folio & paiements.</li>
                            <li>• Housekeeping, maintenance et historique.</li>
                            <li>• Bar/POS, stock et reporting opérationnel.</li>
                            <li>• Support et accompagnement dédiés.</li>
                        </ul>
                    </div>
                    <div class="rounded-3xl border border-serena-border bg-white p-6 shadow-lg">
                        <p class="text-xs font-semibold uppercase text-serena-text-muted">Formule Serena</p>
                        <p class="mt-2 text-3xl font-semibold text-serena-text-main">{{ pricingAmount }}</p>
                        <p class="mt-2 text-xs text-serena-text-muted">Sans engagement, support inclus.</p>
                        <button
                            type="button"
                            class="mt-6 w-full rounded-full bg-[#25b0eb] px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:brightness-105"
                            @click="scrollToDemo"
                        >
                            Demander une démo
                        </button>
                    </div>
                </div>
            </section>

            <section id="demo" class="bg-serena-bg-soft py-16">
                <div class="page-container grid gap-10 lg:grid-cols-[1.1fr_0.9fr] lg:items-start">
                    <div class="space-y-4">
                        <h2 class="text-2xl font-semibold text-serena-text-main">Demander une démo</h2>
                        <p class="text-sm text-serena-text-muted">
                            Dites-nous quelques informations et nous vous recontactons rapidement sur WhatsApp.
                        </p>
                        <div class="rounded-2xl border border-serena-border bg-white p-4 text-sm text-serena-text-muted">
                            <p class="font-semibold text-serena-text-main">Besoin d’une réponse immédiate ?</p>
                            <p class="mt-1">Cliquez pour parler à Serena sur WhatsApp.</p>
                            <a
                                :href="whatsappLink"
                                target="_blank"
                                rel="noreferrer"
                                class="mt-3 inline-flex items-center gap-2 rounded-full bg-[#25b0eb]/10 px-4 py-2 text-xs font-semibold text-[#25b0eb]"
                            >
                                Parler à Serena
                            </a>
                        </div>
                    </div>
                    <div class="rounded-3xl border border-serena-border bg-white p-6 shadow-lg">
                        <form class="space-y-4" @submit.prevent="submitDemo">
                            <div v-if="demoSuccess" class="rounded-2xl bg-[#25b0eb]/10 p-4 text-sm text-serena-text-main">
                                Merci ! Nous vous contactons très vite sur WhatsApp.
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="text-xs font-semibold text-serena-text-muted">Nom de l’hôtel</label>
                                    <input
                                        v-model="form.hotel_name"
                                        type="text"
                                        class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 text-sm focus:border-[#25b0eb] focus:outline-none focus:ring-2 focus:ring-[#25b0eb]/20"
                                        required
                                    />
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-serena-text-muted">Nom complet</label>
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 text-sm focus:border-[#25b0eb] focus:outline-none focus:ring-2 focus:ring-[#25b0eb]/20"
                                        required
                                    />
                                </div>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="text-xs font-semibold text-serena-text-muted">Téléphone WhatsApp</label>
                                    <input
                                        v-model="form.phone"
                                        type="text"
                                        class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 text-sm focus:border-[#25b0eb] focus:outline-none focus:ring-2 focus:ring-[#25b0eb]/20"
                                        required
                                    />
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-serena-text-muted">Ville</label>
                                    <input
                                        v-model="form.city"
                                        type="text"
                                        class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 text-sm focus:border-[#25b0eb] focus:outline-none focus:ring-2 focus:ring-[#25b0eb]/20"
                                    />
                                </div>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="text-xs font-semibold text-serena-text-muted">Email (optionnel)</label>
                                    <input
                                        v-model="form.email"
                                        type="email"
                                        class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 text-sm focus:border-[#25b0eb] focus:outline-none focus:ring-2 focus:ring-[#25b0eb]/20"
                                    />
                                </div>
                                <div class="hidden">
                                    <label class="text-xs font-semibold text-serena-text-muted">Site web</label>
                                    <input v-model="form.website" type="text" tabindex="-1" autocomplete="off" />
                                </div>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-serena-text-muted">Message (optionnel)</label>
                                <textarea
                                    v-model="form.message"
                                    rows="3"
                                    class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 text-sm focus:border-[#25b0eb] focus:outline-none focus:ring-2 focus:ring-[#25b0eb]/20"
                                ></textarea>
                            </div>
                            <button
                                type="submit"
                                class="w-full rounded-full bg-[#25b0eb] px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:brightness-105"
                                :disabled="form.processing"
                            >
                                Envoyer la demande
                            </button>
                        </form>
                    </div>
                </div>
            </section>
        </div>

        <a
            :href="whatsappLink"
            target="_blank"
            rel="noreferrer"
            class="fixed bottom-6 right-6 z-40 inline-flex items-center gap-2 rounded-full bg-[#25b0eb] px-4 py-3 text-xs font-semibold text-white shadow-lg transition hover:brightness-105"
        >
            Parler à Serena
        </a>
    </MarketingLayout>
</template>

<script>
import { Head, Link, useForm } from '@inertiajs/vue3';
import MarketingLayout from '@/layouts/MarketingLayout.vue';

export default {
    name: 'LandingIndex',
    components: {
        Head,
        Link,
        MarketingLayout,
    },
    props: {
        canRegister: {
            type: Boolean,
            default: false,
        },
        pricingAmount: {
            type: String,
            default: '',
        },
        whatsappLink: {
            type: String,
            default: '',
        },
        demoSuccess: {
            type: Boolean,
            default: false,
        },
        scrollTo: {
            type: String,
            default: '',
        },
        screenshots: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            form: useForm({
                hotel_name: '',
                name: '',
                phone: '',
                city: '',
                email: '',
                message: '',
                website: '',
            }),
        };
    },
    mounted() {
        if (this.scrollTo === 'demo' || this.demoSuccess) {
            this.scrollToDemo();
        }
    },
    methods: {
        scrollToDemo() {
            const section = document.getElementById('demo');
            if (section) {
                section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        },
        submitDemo() {
            this.form.post('/demo-request', {
                preserveScroll: true,
                onSuccess: () => {
                    this.form.reset('message');
                },
            });
        },
    },
};
</script>
