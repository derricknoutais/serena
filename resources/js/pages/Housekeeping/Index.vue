<template>
    <AppLayout title="Housekeeping">
        <div class="mx-auto flex max-w-md flex-col gap-4 p-4">
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <h1 class="text-lg font-semibold text-gray-900">Housekeeping</h1>
                <p class="mt-1 text-sm text-gray-500">
                    Scannez une chambre pour mettre à jour son statut.
                </p>
                <button
                    type="button"
                    class="mt-4 w-full rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
                    @click="startScan"
                    :disabled="scanning"
                >
                    {{ scanning ? 'Scanner en cours…' : 'Scanner une chambre' }}
                </button>
            </div>

            <div
                v-if="currentRoom"
                class="rounded-xl bg-white p-4 shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase text-gray-500">Chambre</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            {{ currentRoom.number }}
                        </p>
                        <p class="text-sm text-gray-500">
                            {{ currentRoom.room_type || 'Type inconnu' }}
                            <span v-if="currentRoom.floor"> · Étage {{ currentRoom.floor }}</span>
                        </p>
                    </div>
                    <span
                        class="rounded-full px-3 py-1 text-xs font-semibold"
                        :class="statusBadgeClasses(currentRoom.hk_status)"
                    >
                        {{ currentRoom.hk_status_label }}
                    </span>
                </div>

                <div class="mt-4 rounded-lg bg-gray-50 p-3 text-sm">
                    <p class="font-semibold text-gray-700">
                        Occupation : {{ currentRoom.occupancy?.state || 'Libre' }}
                    </p>
                    <div
                        v-if="currentRoom.occupancy?.reservation"
                        class="mt-2 text-xs text-gray-500"
                    >
                        <p>Code : {{ currentRoom.occupancy.reservation.code || '—' }}</p>
                        <p>Client : {{ currentRoom.occupancy.reservation.guest_name || '—' }}</p>
                        <p>
                            {{ currentRoom.occupancy.reservation.check_in_date }}
                            →
                            {{ currentRoom.occupancy.reservation.check_out_date }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="canManageHousekeeping"
                    class="mt-4 grid gap-2 sm:grid-cols-3"
                >
                    <button
                        type="button"
                        class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700"
                        @click="updateStatus('dirty')"
                        :disabled="loading || !canManageHousekeeping"
                    >
                        Marquer sale
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700"
                        @click="updateStatus('clean')"
                        :disabled="loading || !canManageHousekeeping"
                    >
                        Marquer propre
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm font-semibold text-green-700"
                        @click="updateStatus('inspected')"
                        :disabled="loading || !canManageHousekeeping"
                    >
                        Inspectée
                    </button>
                </div>
                <p
                    v-else
                    class="mt-4 text-sm text-gray-500"
                >
                    Vous n’avez pas les droits pour modifier le statut de cette chambre.
                </p>
            </div>

            <div
                v-else
                class="rounded-xl bg-white p-6 text-sm text-gray-500 shadow-sm"
            >
                Aucune chambre sélectionnée. Scannez un QR code pour commencer.
            </div>
        </div>

        <div
            v-if="scanning"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
        >
            <QrScanner @close="handleScannerClose" @detected="handleQrDetected" />
        </div>
    </AppLayout>
</template>

<script>
    import axios from 'axios';
    import Swal from 'sweetalert2';
    import AppLayout from '@/layouts/AppLayout.vue';
    import QrScanner from '@/components/Housekeeping/QrScanner.vue';

    export default {
        name: 'HousekeepingIndex',
        components: {
            AppLayout,
            QrScanner,
        },
        props: {
            room: {
                type: Object,
                default: null,
            },
            canManageHousekeeping: {
                type: Boolean,
                default: false,
            },
        },
        data() {
            return {
                scanning: false,
                currentRoom: this.room,
                loading: false,
            };
        },
        methods: {
            startScan() {
                this.scanning = true;
            },
            handleScannerClose() {
                this.scanning = false;
            },
            handleQrDetected(value) {
                this.scanning = false;
                const roomId = this.extractRoomId(value);

                if (!roomId) {
                    this.notifyError('QR code invalide.');

                    return;
                }

                this.fetchRoom(roomId);
            },
            extractRoomId(value) {
                if (!value) {
                    return null;
                }

                const trimmed = value.toString().trim();

                try {
                    const url = new URL(trimmed);
                    const segments = url.pathname.split('/').filter(Boolean);

                    return segments.pop() || null;
                } catch (error) {
                    const segments = trimmed.split('/').filter(Boolean);

                    return segments.pop() || null;
                }
            },
            async fetchRoom(roomId) {
                this.loading = true;

                try {
                    const response = await axios.get(`/hk/rooms/${roomId}`);
                    this.currentRoom = response.data.room;
                } catch (error) {
                    this.notifyError('Impossible de charger cette chambre.');
                } finally {
                    this.loading = false;
                }
            },
            async updateStatus(status) {
                if (!this.canManageHousekeeping) {
                    this.notifyError('Vous n’avez pas les droits nécessaires.');

                    return;
                }

                if (!this.currentRoom || this.loading) {
                    return;
                }

                this.loading = true;

                try {
                    const response = await axios.patch(`/hk/rooms/${this.currentRoom.id}/status`, {
                        hk_status: status,
                    });

                    this.currentRoom = response.data.room;
                    this.notifySuccess('Statut mis à jour.');
                } catch (error) {
                    this.notifyError("Impossible de mettre à jour le statut.");
                } finally {
                    this.loading = false;
                }
            },
            statusBadgeClasses(status) {
                switch (status) {
                    case 'dirty':
                        return 'bg-amber-50 text-amber-700 border border-amber-200';
                    case 'inspected':
                        return 'bg-green-50 text-green-700 border border-green-200';
                    case 'clean':
                    default:
                        return 'bg-blue-50 text-blue-700 border border-blue-200';
                }
            },
            notifySuccess(message) {
                Swal.fire({
                    icon: 'success',
                    title: message,
                    timer: 1500,
                    showConfirmButton: false,
                });
            },
            notifyError(message) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: message,
                });
            },
        },
    };
</script>
