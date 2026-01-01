<template>
    <div>
        <!-- Loading State -->
        <div v-if="isLoading" class="flex items-center space-x-2 text-sm text-gray-400">
            <span class="animate-pulse">Chargement caisse...</span>
        </div>

        <!-- Closed State -->
        <div v-else-if="!session" class="flex items-center space-x-3">
            <span class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/10">
                Caisse fermée
            </span>
            <PrimaryButton
                v-if="canOpenCash"
                @click="openingModal = true"
                type="button"
                class="px-3 py-1.5 text-xs"
            >
                Ouvrir la caisse
            </PrimaryButton>
            <span v-else class="text-xs text-gray-400">Ouverture non autorisée</span>
        </div>

        <!-- Open State -->
        <div v-else class="flex items-center space-x-3">
            <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                Caisse ouverte
            </span>
            <div class="flex flex-col text-xs">
                <span class="font-medium text-gray-900">{{ session.currency }} {{ formatNumber(session.calculated_balance || session.theoretical_balance || 0) }}</span>
                <span v-if="session.total_received !== undefined" class="text-xs text-gray-500">
                    Total encaissé : {{ formatNumber(session.total_received) }}
                </span>
                <span class="text-gray-500">Ouvert par {{ session.opened_by?.name || 'Moi' }}</span>
            </div>
            <button
                v-if="canCloseCash"
                @click="closingModal = true"
                type="button"
                class="text-xs font-medium text-gray-500 hover:text-gray-700"
            >
                Fermer
            </button>
            <span v-else class="text-xs text-gray-400">Fermeture non autorisée</span>
        </div>

        <!-- Opening Modal -->
        <Dialog :open="openingModal" @update:open="openingModal = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Ouvrir la caisse ({{ type === 'frontdesk' ? 'Réception' : 'Bar' }})</DialogTitle>
                </DialogHeader>
                <div class="mt-4">
                    <form @submit.prevent="openSession">
                        <div>
                            <Label for="starting_amount">Fond de caisse initial</Label>
                            <TextInput
                                id="starting_amount"
                                ref="startingInput"
                                v-model="openForm.starting_amount"
                                type="number"
                                step="0.01"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError :message="openForm.errors.starting_amount" class="mt-2" />
                            <InputError :message="openForm.errors.session" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <Label for="notes">Note (Optionnel)</Label>
                            <textarea
                                id="notes"
                                v-model="openForm.notes"
                                class="mt-1 block w-full rounded-lg border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main shadow-sm transition focus:border-serena-primary focus:ring-2 focus:ring-serena-primary-soft focus-visible:outline-none disabled:cursor-not-allowed disabled:bg-serena-bg-soft disabled:text-serena-text-muted"
                                rows="2"
                            ></textarea>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <SecondaryButton @click="openingModal = false"> Annuler </SecondaryButton>
                            <PrimaryButton
                                class="ml-3"
                                :class="{ 'opacity-25': openForm.processing }"
                                :disabled="openForm.processing"
                                type="submit"
                            >
                                Ouvrir la caisse
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Closing Modal -->
        <Dialog :open="closingModal" @update:open="closingModal = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Fermer la caisse</DialogTitle>
                </DialogHeader>
                <p class="mt-1 text-sm text-gray-500">
                    Veuillez compter le montant total en espèces présent dans le tiroir.
                </p>
                <p class="text-sm font-medium text-green-700">
                    Solde actuel : {{ formatNumber(session.theoretical_balance) }}
                </p>
                <p v-if="session.total_received !== undefined" class="text-xs text-gray-500">
                    Total encaissé : {{ formatNumber(session.total_received) }}
                </p>

                <div class="mt-4">
                    <form @submit.prevent="closeSession">
                        <div>
                            <Label for="closing_amount">Montant compté (Espèces)</Label>
                            <TextInput
                                id="closing_amount"
                                ref="closingInput"
                                v-model="closeForm.closing_amount"
                                type="number"
                                step="0.01"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError :message="closeForm.errors.closing_amount" class="mt-2" />
                        </div>
                        
                        <div class="mt-4">
                            <Label for="close_notes">Note de fermeture</Label>
                            <textarea
                                id="close_notes"
                                v-model="closeForm.notes"
                                class="mt-1 block w-full rounded-lg border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main shadow-sm transition focus:border-serena-primary focus:ring-2 focus:ring-serena-primary-soft focus-visible:outline-none disabled:cursor-not-allowed disabled:bg-serena-bg-soft disabled:text-serena-text-muted"
                                placeholder="Justification d'écart, commentaire..."
                                rows="3"
                            ></textarea>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <SecondaryButton @click="closingModal = false"> Annuler </SecondaryButton>
                            <PrimaryButton
                                class="ml-3 bg-red-600 hover:bg-red-500 focus:bg-red-700 active:bg-red-900"
                                :class="{ 'opacity-25': closeForm.processing }"
                                :disabled="closeForm.processing"
                                type="submit"
                            >
                                Fermer la caisse
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>

<script>
import { router, useForm } from '@inertiajs/vue3';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import Label from '@/components/ui/label/Label.vue';
import TextInput from '@/components/TextInput.vue';
import InputError from '@/components/InputError.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import axios from 'axios';
import Swal from 'sweetalert2';

export default {
    name: 'CashIndicator',
    components: {
        Dialog,
        DialogContent,
        DialogHeader,
        DialogTitle,
        Label,
        TextInput,
        InputError,
        PrimaryButton,
        SecondaryButton,
    },
    props: {
        type: {
            type: String,
            required: true,
            validator: (value) => ['frontdesk', 'bar'].includes(value),
        },
    },
    data() {
        return {
            session: null,
            isLoading: true,
            openingModal: false,
            closingModal: false,
            openForm: useForm({
                starting_amount: '',
                type: this.type,
                notes: '',
            }),
            closeForm: useForm({
                closing_amount: '',
                notes: '',
            }),
        };
    },
    computed: {
        permissions() {
            return this.$page?.props?.auth?.can ?? {};
        },
        canOpenCash() {
            return this.permissions.cash_sessions_open ?? false;
        },
        canCloseCash() {
            return this.permissions.cash_sessions_close ?? false;
        },
    },
    mounted() {
        this.fetchSession();
        window.addEventListener('cash-session-updated', this.handleExternalUpdate);
        window.addEventListener('cash-session-open-request', this.handleOpenRequest);
    },
    beforeUnmount() {
        window.removeEventListener('cash-session-updated', this.handleExternalUpdate);
        window.removeEventListener('cash-session-open-request', this.handleOpenRequest);
    },
    methods: {
        showUnauthorizedAlert() {
            Swal.fire({
                icon: 'error',
                title: 'Action non autorisée',
                text: 'Vous ne disposez pas des droits suffisants.',
            });
        },
        handleExternalUpdate(event) {
            const eventType = event?.detail?.type || 'frontdesk';

            if (eventType !== this.type) {
                return;
            }

            this.fetchSession();
        },
        handleOpenRequest(event) {
            const eventType = event?.detail?.type || 'frontdesk';

            if (eventType !== this.type) {
                return;
            }

            if (!this.canOpenCash) {
                this.showUnauthorizedAlert();

                return;
            }

            this.openingModal = true;
        },
        async fetchSession() {
            try {
                this.isLoading = true;
                const response = await axios.get('/cash/status', { params: { type: this.type } });
                this.session = response.data.session;
            } catch (error) {
                console.error('Failed to fetch cash session status', error);
            } finally {
                this.isLoading = false;
            }
        },
        formatNumber(num) {
            return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 0 }).format(num);
        },
        openSession() {
            if (!this.canOpenCash) {
                this.showUnauthorizedAlert();

                return;
            }
            this.openForm.type = this.type; // Ensure type is correct
            this.openForm.post('/cash', {
                preserveScroll: true,
                onSuccess: () => {
                    this.openingModal = false;
                    this.fetchSession(); 
                    this.openForm.reset();
                },
                onError: (errors) => {
                    if (!errors || Object.keys(errors).length === 0) {
                        this.showUnauthorizedAlert();
                    }
                },
            });
        },
        closeSession() {
            if (!this.session) return;
            
            if (!this.canCloseCash) {
                this.showUnauthorizedAlert();

                return;
            }
            
            this.closeForm.post('/cash/' + this.session.id + '/close', {
                preserveScroll: true,
                onSuccess: () => {
                    this.closingModal = false;
                    this.fetchSession();
                    this.closeForm.reset();
                },
                onError: (errors) => {
                    if (!errors || Object.keys(errors).length === 0) {
                        this.showUnauthorizedAlert();
                    }
                },
            });
        },
    },
};
</script>
