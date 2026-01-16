<template>
    <AppLayout title="Maintenance">
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-semibold text-serena-text-main">
                    Maintenance
                </h1>
                <p class="text-sm text-serena-text-muted">
                    Suivi des tickets et interventions de maintenance.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 rounded-xl bg-white p-2 shadow-sm">
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-semibold transition cursor-pointer"
                        :class="tabButtonClasses('tickets')"
                        @click="switchTab('tickets')"
                    >
                        Tickets
                    </button>
                    <button
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-semibold transition cursor-pointer"
                        :class="tabButtonClasses('interventions')"
                        @click="switchTab('interventions')"
                    >
                        Interventions
                    </button>
                </div>
                <div class="ml-auto flex flex-wrap gap-2">
                    <button
                        v-if="activeTabValue === 'tickets' && canCreateTicket"
                        type="button"
                        class="rounded-xl bg-serena-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-serena-primary-dark"
                        @click="openCreateTicketModal"
                    >
                        Signaler une panne
                    </button>
                    <button
                        v-if="activeTabValue === 'interventions' && canCreateIntervention"
                        type="button"
                        class="rounded-xl bg-serena-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-serena-primary-dark"
                        @click="openCreateInterventionModal"
                    >
                        Nouvelle intervention
                    </button>
                </div>
            </div>

            <div v-if="activeTabValue === 'tickets'" class="space-y-4">
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="option in statusOptions"
                        :key="option"
                        type="button"
                        class="rounded-full px-3 py-1.5 text-sm font-semibold transition"
                        :class="statusButtonClasses(option)"
                        @click="changeStatusFilter(option)"
                    >
                        {{ statusFilterLabel(option) }}
                    </button>
                </div>

                <div class="grid gap-3 rounded-2xl border border-serena-border bg-white p-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="text-xs font-semibold text-serena-text-muted">
                            Chambre
                        </label>
                        <select
                            v-model="ticketFilters.room_id"
                            class="mt-1 w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                            @change="applyFilters"
                        >
                            <option value="">Toutes</option>
                            <option v-for="room in roomOptions" :key="room.id" :value="room.id">
                                {{ roomLabel(room) }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-serena-text-muted">
                            Type
                        </label>
                        <Multiselect
                            v-model="ticketFilters.maintenance_type"
                            :options="maintenanceTypeOptions"
                            track-by="id"
                            label="name"
                            placeholder="Tous"
                            class="mt-1"
                            @update:model-value="applyFilters"
                        />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-serena-text-muted">
                            Sévérité
                        </label>
                        <select
                            v-model="ticketFilters.severity"
                            class="mt-1 w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                            @change="applyFilters"
                        >
                            <option value="">Toutes</option>
                            <option v-for="option in ticketSeverityOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-serena-text-muted">
                            Blocage
                        </label>
                        <select
                            v-model="ticketFilters.blocks_sale"
                            class="mt-1 w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                            @change="applyFilters"
                        >
                            <option value="">Tous</option>
                            <option value="1">Bloquants</option>
                            <option value="0">Non bloquants</option>
                        </select>
                    </div>
                </div>

                <div v-if="!hasTickets" class="rounded-2xl border border-dashed border-serena-border bg-white/70 p-8 text-center text-sm text-serena-text-muted">
                    Aucun ticket de maintenance pour le moment.
                </div>

                <div v-else class="space-y-4">
                    <div class="space-y-4 sm:hidden">
                        <article
                            v-for="ticket in tickets.data"
                            :key="ticket.id"
                            class="rounded-2xl border border-serena-border bg-white p-4 shadow-sm"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-base font-semibold text-serena-text-main">
                                        {{ ticket.title }}
                                    </h3>
                                    <p class="text-xs text-serena-text-muted">
                                        {{ ticket.room ? `Chambre ${ticket.room.number}` : 'Chambre supprimée' }}
                                        <span v-if="ticket.room?.room_type_name" class="text-serena-text-muted/80">
                                            · {{ ticket.room.room_type_name }}
                                        </span>
                                    </p>
                                </div>
                                <div class="flex flex-col items-end gap-2">
                                    <span class="rounded-full px-3 py-0.5 text-[11px] font-semibold" :class="statusClasses(ticket.status)">
                                        {{ statusLabel(ticket.status) }}
                                    </span>
                                    <span class="rounded-full px-3 py-0.5 text-[11px] font-semibold" :class="severityClasses(ticket.severity)">
                                        {{ severityLabel(ticket.severity) }}
                                    </span>
                                </div>
                            </div>

                            <div class="mt-3 space-y-1 text-xs text-serena-text-muted">
                                <p>
                                    Ouvert le
                                    <span class="font-semibold text-serena-text-main">
                                        {{ formatDateTime(ticket.opened_at) }}
                                    </span>
                                </p>
                                <p>
                                    Assigné à :
                                    <span class="font-semibold text-serena-text-main">
                                        {{ ticket.assigned_to?.name ?? 'Non assigné' }}
                                    </span>
                                </p>
                                <p v-if="ticket.maintenance_type?.name">
                                    Type :
                                    <span class="font-semibold text-serena-text-main">
                                        {{ ticket.maintenance_type.name }}
                                    </span>
                                </p>
                            </div>

                            <div class="mt-4 flex justify-end">
                                <button
                                    type="button"
                                    class="text-sm font-semibold text-serena-primary transition hover:text-serena-primary-dark"
                                    @click="openTicket(ticket)"
                                >
                                    Voir
                                </button>
                            </div>
                        </article>
                    </div>

                    <div class="hidden overflow-x-auto rounded-2xl border border-serena-border bg-white shadow-sm sm:block">
                        <table class="w-full min-w-[860px] divide-y divide-serena-border text-sm">
                            <thead class="bg-serena-bg-soft/70 text-left text-xs font-semibold uppercase tracking-wide text-serena-text-muted">
                                <tr>
                                    <th class="px-4 py-3">Ticket</th>
                                    <th class="px-4 py-3">Chambre</th>
                                    <th class="px-4 py-3">Type</th>
                                    <th class="px-4 py-3">Statut</th>
                                    <th class="px-4 py-3">Sévérité</th>
                                    <th class="px-4 py-3">Ouvert le</th>
                                    <th class="px-4 py-3">Assigné à</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-serena-border/60 text-serena-text-main">
                                <tr v-for="ticket in tickets.data" :key="ticket.id">
                                    <td class="px-4 py-3">
                                        <p class="font-semibold">
                                            {{ ticket.title }}
                                        </p>
                                        <p class="text-xs text-serena-text-muted">
                                            {{ ticket.description || 'Pas de description.' }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        <p class="font-semibold">
                                            {{ ticket.room ? `#${ticket.room.number}` : '—' }}
                                        </p>
                                        <p class="text-serena-text-muted">
                                            {{ ticket.room?.room_type_name || 'Type inconnu' }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        {{ ticket.maintenance_type?.name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-3 py-0.5 text-[11px] font-semibold" :class="statusClasses(ticket.status)">
                                            {{ statusLabel(ticket.status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-3 py-0.5 text-[11px] font-semibold" :class="severityClasses(ticket.severity)">
                                            {{ severityLabel(ticket.severity) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        {{ formatDateTime(ticket.opened_at) }}
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        {{ ticket.assigned_to?.name ?? 'Non assigné' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            class="text-xs font-semibold text-serena-primary transition hover:text-serena-primary-dark"
                                            @click="openTicket(ticket)"
                                        >
                                            Voir
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="tickets.links?.length" class="flex flex-wrap gap-2 text-sm">
                        <button
                            v-for="link in tickets.links"
                            :key="link.label"
                            type="button"
                            class="rounded-full px-3 py-1 transition"
                            :class="linkClasses(link)"
                            :disabled="!link.url"
                            @click.prevent="visitLink(link.url)"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>

            <div v-else class="space-y-4">
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-for="option in interventionStatusOptions"
                        :key="option"
                        type="button"
                        class="rounded-full px-3 py-1.5 text-sm font-semibold transition"
                        :class="interventionStatusButtonClasses(option)"
                        @click="changeInterventionStatusFilter(option)"
                    >
                        {{ interventionStatusLabel(option) }}
                    </button>
                    <button
                        type="button"
                        class="rounded-full border border-amber-300 bg-amber-50 px-3 py-1.5 text-sm font-semibold text-amber-800 transition hover:bg-amber-100"
                        @click="setValidationFilter"
                    >
                        A valider
                    </button>
                </div>

                <div class="grid gap-3 rounded-2xl border border-serena-border bg-white p-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="text-xs font-semibold text-serena-text-muted">
                            Technicien
                        </label>
                        <select
                            v-model="interventionFilters.technician_id"
                            class="mt-1 w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                            @change="applyFilters"
                        >
                            <option value="">Tous</option>
                            <option v-for="tech in technicianOptions" :key="tech.id" :value="tech.id">
                                {{ tech.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-serena-text-muted">
                            Chambre
                        </label>
                        <select
                            v-model="ticketFilters.room_id"
                            class="mt-1 w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                            @change="applyFilters"
                        >
                            <option value="">Toutes</option>
                            <option v-for="room in roomOptions" :key="room.id" :value="room.id">
                                {{ roomLabel(room) }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-serena-text-muted">
                            Du
                        </label>
                        <input
                            v-model="interventionFilters.from"
                            type="date"
                            class="mt-1 w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                            @change="applyFilters"
                        />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-serena-text-muted">
                            Au
                        </label>
                        <input
                            v-model="interventionFilters.to"
                            type="date"
                            class="mt-1 w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                            @change="applyFilters"
                        />
                    </div>
                </div>

                <div v-if="!hasInterventions" class="rounded-2xl border border-dashed border-serena-border bg-white/70 p-8 text-center text-sm text-serena-text-muted">
                    Aucune intervention enregistrée pour le moment.
                </div>

                <div v-else class="space-y-4">
                    <div class="space-y-4 sm:hidden">
                        <article
                            v-for="intervention in interventions.data"
                            :key="intervention.id"
                            class="rounded-2xl border border-serena-border bg-white p-4 shadow-sm cursor-pointer"
                            @click="visitInterventionDetail(intervention)"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-base font-semibold text-serena-text-main">
                                        {{ interventionTitle(intervention) }}
                                    </h3>
                                    <p class="text-xs text-serena-text-muted">
                                        {{ intervention.rooms?.length ? `Ch ${intervention.rooms.join(', ')}` : 'Chambres multiples' }}
                                    </p>
                                </div>
                                <span class="rounded-full px-3 py-0.5 text-[11px] font-semibold" :class="interventionStatusClasses(intervention.accounting_status)">
                                    {{ interventionStatusLabel(intervention.accounting_status) }}
                                </span>
                            </div>

                            <div class="mt-3 space-y-1 text-xs text-serena-text-muted">
                                <p>
                                    Technicien :
                                    <span class="font-semibold text-serena-text-main">
                                        {{ intervention.technician?.name ?? 'Non assigné' }}
                                    </span>
                                </p>
                                <p>
                                    Total :
                                    <span class="font-semibold text-serena-text-main">
                                        {{ formatAmount(intervention.estimated_total_amount ?? intervention.total_cost, intervention.currency) }}
                                    </span>
                                </p>
                                <p v-if="intervention.started_at">
                                    Début : {{ formatDateTime(intervention.started_at) }}
                                </p>
                            </div>

                                <div class="mt-4 flex flex-wrap justify-end gap-2">
                                    <button
                                        v-if="canApproveIntervention && intervention.accounting_status === 'submitted'"
                                        type="button"
                                        class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700"
                                        @click.stop="approveIntervention(intervention)"
                                    >
                                        Approuver
                                    </button>
                                    <button
                                    v-if="canRejectIntervention && intervention.accounting_status === 'submitted'"
                                    type="button"
                                    class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700"
                                        @click.stop="promptRejectIntervention(intervention)"
                                    >
                                        Rejeter
                                    </button>
                                    <button
                                        type="button"
                                        class="text-sm font-semibold text-serena-primary transition hover:text-serena-primary-dark"
                                        @click.stop="visitInterventionDetail(intervention)"
                                    >
                                        Voir
                                    </button>
                                </div>
                        </article>
                    </div>

                    <div class="hidden overflow-x-auto rounded-2xl border border-serena-border bg-white shadow-sm sm:block">
                        <table class="w-full min-w-[860px] divide-y divide-serena-border text-sm">
                            <thead class="bg-serena-bg-soft/70 text-left text-xs font-semibold uppercase tracking-wide text-serena-text-muted">
                                <tr>
                                    <th class="px-4 py-3">Intervention</th>
                                    <th class="px-4 py-3">Chambres</th>
                                    <th class="px-4 py-3">Technicien</th>
                                    <th class="px-4 py-3">Statut</th>
                                    <th class="px-4 py-3">Total</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-serena-border/60 text-serena-text-main">
                                <tr
                                    v-for="intervention in interventions.data"
                                    :key="intervention.id"
                                    class="cursor-pointer hover:bg-serena-bg-soft/50"
                                    @click="visitInterventionDetail(intervention)"
                                >
                                    <td class="px-4 py-3">
                                        <p class="font-semibold">
                                            {{ interventionTitle(intervention) }}
                                        </p>
                                        <p class="text-xs text-serena-text-muted">
                                            {{ intervention.started_at ? `Début ${formatDateTime(intervention.started_at)}` : 'Date non renseignée' }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        {{ intervention.rooms?.length ? intervention.rooms.join(', ') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        {{ intervention.technician?.name ?? 'Non assigné' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-3 py-0.5 text-[11px] font-semibold" :class="interventionStatusClasses(intervention.accounting_status)">
                                            {{ interventionStatusLabel(intervention.accounting_status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        {{ formatAmount(intervention.estimated_total_amount ?? intervention.total_cost, intervention.currency) }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button
                                                v-if="canApproveIntervention && intervention.accounting_status === 'submitted'"
                                                type="button"
                                                class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700"
                                                @click.stop="approveIntervention(intervention)"
                                            >
                                                Approuver
                                            </button>
                                            <button
                                                v-if="canRejectIntervention && intervention.accounting_status === 'submitted'"
                                                type="button"
                                                class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700"
                                                @click.stop="promptRejectIntervention(intervention)"
                                            >
                                                Rejeter
                                            </button>
                                            <button
                                                type="button"
                                                class="text-xs font-semibold text-serena-primary transition hover:text-serena-primary-dark"
                                                @click.stop="visitInterventionDetail(intervention)"
                                            >
                                                Voir
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="interventions.links?.length" class="flex flex-wrap gap-2 text-sm">
                        <button
                            v-for="link in interventions.links"
                            :key="link.label"
                            type="button"
                            class="rounded-full px-3 py-1 transition"
                            :class="linkClasses(link)"
                            :disabled="!link.url"
                            @click.prevent="visitLink(link.url)"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="showTicketModal"
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/60 px-4"
        >
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-serena-text-main">
                            {{ ticketModalTitle }}
                        </h3>
                        <p v-if="ticketFormMode === 'edit'" class="text-xs text-serena-text-muted">
                            {{ selectedTicket?.room ? `Chambre ${selectedTicket.room.number}` : 'Chambre inconnue' }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="text-sm text-serena-text-muted transition hover:text-serena-text-main"
                        @click="closeTicketModal"
                    >
                        Fermer
                    </button>
                </div>

                <div class="space-y-4">
                    <div v-if="ticketFormMode === 'create'" class="grid gap-4">
                        <div>
                            <label class="text-xs font-semibold text-serena-text-muted">
                                Chambre
                            </label>
                            <select
                                v-model="ticketForm.room_id"
                                class="mt-1 w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                            >
                                <option value="">Sélectionner</option>
                                <option v-for="room in roomOptions" :key="room.id" :value="room.id">
                                    {{ roomLabel(room) }}
                                </option>
                            </select>
                            <p v-if="ticketErrors.room_id" class="mt-1 text-xs text-serena-danger">
                                {{ ticketErrors.room_id }}
                            </p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-serena-text-muted">
                                Titre
                            </label>
                            <input
                                v-model="ticketForm.title"
                                type="text"
                                class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                            />
                            <p v-if="ticketErrors.title" class="mt-1 text-xs text-serena-danger">
                                {{ ticketErrors.title }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-semibold text-serena-text-muted">
                            Type
                        </label>
                        <Multiselect
                            v-model="ticketForm.maintenance_type"
                            :options="maintenanceTypeOptions"
                            track-by="id"
                            label="name"
                            placeholder="Autre"
                            class="mt-1"
                        />
                        <p v-if="ticketErrors.maintenance_type_id" class="mt-1 text-xs text-serena-danger">
                            {{ ticketErrors.maintenance_type_id }}
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold text-serena-text-muted">
                                Sévérité
                            </label>
                            <select
                                v-model="ticketForm.severity"
                                class="mt-1 w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                            >
                                <option v-for="option in ticketSeverityOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <p v-if="ticketErrors.severity" class="mt-1 text-xs text-serena-danger">
                                {{ ticketErrors.severity }}
                            </p>
                        </div>
                        <div v-if="canOverrideBlocksSale">
                            <label class="text-xs font-semibold text-serena-text-muted">
                                Bloque vente
                            </label>
                            <div class="mt-2 flex items-center gap-2">
                                <input
                                    id="ticket-blocks-sale"
                                    v-model="ticketForm.blocks_sale"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-serena-border text-serena-primary focus:ring-serena-primary"
                                />
                                <label for="ticket-blocks-sale" class="text-sm text-serena-text-main">
                                    Bloquant
                                </label>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-semibold text-serena-text-muted">
                            Description
                        </label>
                        <textarea
                            v-model="ticketForm.description"
                            rows="3"
                            class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                        ></textarea>
                        <p v-if="ticketErrors.description" class="mt-1 text-xs text-serena-danger">
                            {{ ticketErrors.description }}
                        </p>
                    </div>

                    <div v-if="ticketFormMode === 'edit'" class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold text-serena-text-muted">
                                Statut
                            </label>
                            <select
                                v-model="ticketForm.status"
                                class="mt-1 w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft disabled:bg-serena-bg-soft"
                                :disabled="!canUpdateStatus"
                            >
                                <option value="open">Ouvert</option>
                                <option value="in_progress">En cours</option>
                                <option value="resolved">Résolu</option>
                                <option value="closed">Clôturé</option>
                            </select>
                            <p v-if="ticketErrors.status" class="mt-1 text-xs text-serena-danger">
                                {{ ticketErrors.status }}
                            </p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-serena-text-muted">
                                Assigné à
                            </label>
                            <select
                                v-model="ticketForm.assigned_to_user_id"
                                class="mt-1 w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft disabled:bg-serena-bg-soft"
                                :disabled="!canAssign"
                            >
                                <option :value="null">Non assigné</option>
                                <option v-for="user in assignableUsers" :key="user.id" :value="user.id">
                                    {{ user.name }}
                                </option>
                            </select>
                            <p v-if="ticketErrors.assigned_to_user_id" class="mt-1 text-xs text-serena-danger">
                                {{ ticketErrors.assigned_to_user_id }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        class="text-sm text-serena-text-muted transition hover:text-serena-text-main"
                        @click="closeTicketModal"
                    >
                        Annuler
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-serena-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-serena-primary-dark disabled:opacity-60"
                        :disabled="ticketSubmitting"
                        @click="submitTicketForm"
                    >
                        {{ ticketSubmitting ? 'Enregistrement...' : 'Enregistrer' }}
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="showInterventionModal"
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/60 px-4"
        >
            <div class="w-full max-w-5xl rounded-2xl bg-white p-6 shadow-xl">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-serena-text-main">
                            {{ interventionForm.id ? `Intervention #${interventionForm.id}` : 'Nouvelle intervention' }}
                        </h3>
                        <p class="text-xs text-serena-text-muted">
                            {{ interventionForm.accounting_status ? interventionStatusLabel(interventionForm.accounting_status) : 'Brouillon' }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="text-sm text-serena-text-muted transition hover:text-serena-text-main"
                        @click="closeInterventionModal"
                    >
                        Fermer
                    </button>
                </div>

                <div class="space-y-6">
                    <section class="space-y-4">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">
                            Informations
                        </h4>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-xs font-semibold text-serena-text-muted">
                                    Technicien
                                </label>
                                <Multiselect
                                    v-model="interventionForm.technician"
                                    :options="technicianOptions"
                                    track-by="id"
                                    label="name"
                                    placeholder="Non assigné"
                                    class="mt-1"
                                    :disabled="!canEditIntervention"
                                />
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-serena-text-muted">
                                    Devise
                                </label>
                                <input
                                    v-model="interventionForm.currency"
                                    type="text"
                                    maxlength="3"
                                    class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 text-sm uppercase text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft disabled:bg-serena-bg-soft"
                                    :disabled="!canEditIntervention"
                                />
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-serena-text-muted">
                                    Début
                                </label>
                                <input
                                    v-model="interventionForm.started_at"
                                    type="datetime-local"
                                    class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft disabled:bg-serena-bg-soft"
                                    :disabled="!canEditIntervention"
                                />
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-serena-text-muted">
                                    Fin
                                </label>
                                <input
                                    v-model="interventionForm.ended_at"
                                    type="datetime-local"
                                    class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft disabled:bg-serena-bg-soft"
                                    :disabled="!canEditIntervention"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-serena-text-muted">
                                Résumé
                            </label>
                            <textarea
                                v-model="interventionForm.summary"
                                rows="3"
                                class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft disabled:bg-serena-bg-soft"
                                :disabled="!canEditIntervention"
                            ></textarea>
                            <p v-if="interventionErrors.summary" class="mt-1 text-xs text-serena-danger">
                                {{ interventionErrors.summary }}
                            </p>
                        </div>
                    </section>

                    <section class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">
                                Tickets
                            </h4>
                            <span class="text-xs text-serena-text-muted">
                                {{ selectedInterventionTickets.length }} sélectionné(s)
                            </span>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-serena-text-muted">
                                Tickets liés
                            </label>
                            <Multiselect
                                v-model="selectedInterventionTickets"
                                :options="ticketOptions"
                                track-by="id"
                                :custom-label="ticketOptionLabel"
                                placeholder="Sélectionner des tickets"
                                class="mt-1"
                                :multiple="true"
                                :close-on-select="false"
                                :disabled="!canEditIntervention"
                            />
                            <p v-if="interventionErrors.tickets" class="mt-1 text-xs text-serena-danger">
                                {{ interventionErrors.tickets }}
                            </p>
                        </div>

                        <div v-if="selectedInterventionTickets.length" class="space-y-3">
                            <div
                                v-for="ticket in selectedInterventionTickets"
                                :key="ticket.id"
                                class="rounded-xl border border-serena-border bg-serena-bg-soft/40 p-3"
                            >
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-semibold text-serena-text-main">
                                        {{ ticketOptionLabel(ticket) }}
                                    </p>
                                </div>
                                <textarea
                                    v-model="interventionTicketDetails[ticket.id].work_done"
                                    rows="2"
                                    class="mt-2 w-full rounded-lg border border-serena-border bg-white px-3 py-2 text-xs text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft disabled:bg-serena-bg-soft"
                                    :placeholder="'Travaux effectués'"
                                    :disabled="!canEditIntervention"
                                ></textarea>
                            </div>
                        </div>
                    </section>

                    <section class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">
                                    Coûts (estimés)
                                </h4>
                                <p class="text-[11px] text-serena-text-muted">
                                    Info interne : aucune incidence sur la caisse ou les paiements.
                                </p>
                            </div>
                            <button
                                v-if="canEditCostLines && !isInterventionLocked"
                                type="button"
                                class="rounded-lg border border-serena-border bg-white px-3 py-1 text-xs font-semibold text-serena-text-main hover:bg-serena-bg-soft"
                                @click="openCostLineModal"
                            >
                                Ajouter une ligne
                            </button>
                        </div>

                        <div v-if="!canViewCostLines" class="rounded-xl border border-dashed border-serena-border bg-serena-bg-soft/40 p-4 text-center text-xs text-serena-text-muted">
                            Vous n’avez pas accès aux coûts estimés.
                        </div>

                        <div v-else-if="!interventionCosts.length" class="rounded-xl border border-dashed border-serena-border bg-serena-bg-soft/40 p-4 text-center text-xs text-serena-text-muted">
                            Aucun coût estimé ajouté pour le moment.
                        </div>

                        <div v-else class="overflow-x-auto rounded-xl border border-serena-border">
                            <table class="min-w-full divide-y divide-serena-border text-xs">
                                <thead class="bg-serena-bg-soft/70 text-left text-[10px] font-semibold uppercase text-serena-text-muted">
                                    <tr>
                                        <th class="px-3 py-2">Type</th>
                                        <th class="px-3 py-2">Libellé</th>
                                        <th class="px-3 py-2 text-right">Qté</th>
                                        <th class="px-3 py-2 text-right">PU estimé</th>
                                        <th class="px-3 py-2 text-right">Total estimé</th>
                                        <th class="px-3 py-2 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-serena-border/60 text-serena-text-main">
                                    <tr v-for="line in interventionCosts" :key="line.id">
                                        <td class="px-3 py-2">
                                            {{ costTypeLabel(line.cost_type) }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="flex flex-col gap-1">
                                                <span>{{ line.label }}</span>
                                                <span
                                                    v-if="line.source === 'stock'"
                                                    class="inline-flex w-fit items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700"
                                                >
                                                    Stock (estimé)
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            {{ formatQuantity(line.quantity) }}
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            {{ formatAmount(line.unit_price, line.currency) }}
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            {{ formatAmount(line.total_amount, line.currency) }}
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <div class="flex justify-end gap-2">
                                                <button
                                                    v-if="canEditCostLines && !isInterventionLocked && line.source !== 'stock'"
                                                    type="button"
                                                    class="text-xs font-semibold text-serena-primary hover:text-serena-primary-dark"
                                                    @click="openCostLineModal(line)"
                                                >
                                                    Modifier
                                                </button>
                                                <button
                                                    v-if="canEditCostLines && !isInterventionLocked && line.source !== 'stock'"
                                                    type="button"
                                                    class="text-xs font-semibold text-rose-600 hover:text-rose-700"
                                                    @click="deleteCostLine(line)"
                                                >
                                                    Supprimer
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="flex justify-end text-sm font-semibold text-serena-text-main">
                            Total estimé : {{ formatAmount(interventionComputedTotal, interventionForm.currency) }}
                        </div>
                    </section>
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-if="canSubmitIntervention && interventionForm.id && !isInterventionLocked"
                            type="button"
                            class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700"
                            :disabled="interventionSubmitting"
                            @click="submitIntervention"
                        >
                            Soumettre
                        </button>
                        <button
                            v-if="canApproveIntervention && interventionForm.accounting_status === 'submitted'"
                            type="button"
                            class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700"
                            :disabled="interventionSubmitting"
                            @click="approveIntervention(interventionForm)"
                        >
                            Approuver
                        </button>
                        <button
                            v-if="canRejectIntervention && interventionForm.accounting_status === 'submitted'"
                            type="button"
                            class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700"
                            :disabled="interventionSubmitting"
                            @click="promptRejectIntervention(interventionForm)"
                        >
                            Rejeter
                        </button>
                        <button
                            v-if="canMarkPaidIntervention && interventionForm.accounting_status === 'approved'"
                            type="button"
                            class="rounded-lg border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-700"
                            :disabled="interventionSubmitting"
                            @click="markPaidIntervention(interventionForm)"
                        >
                            Marquer payé
                        </button>
                    </div>
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="text-sm text-serena-text-muted transition hover:text-serena-text-main"
                            @click="closeInterventionModal"
                        >
                            Annuler
                        </button>
                        <button
                            v-if="canEditIntervention"
                            type="button"
                            class="rounded-xl bg-serena-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-serena-primary-dark disabled:opacity-60"
                            :disabled="interventionSubmitting"
                            @click="saveIntervention"
                        >
                            {{ interventionSubmitting ? 'Enregistrement...' : 'Enregistrer' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="showCostLineModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4"
        >
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-serena-text-main">
                            {{ costLineForm.id ? 'Modifier la ligne' : 'Nouvelle ligne de coût' }}
                        </h3>
                        <p class="text-xs text-serena-text-muted">
                            {{ interventionForm.id ? `Intervention #${interventionForm.id}` : '' }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="text-sm text-serena-text-muted transition hover:text-serena-text-main"
                        @click="closeCostLineModal"
                    >
                        Fermer
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="text-xs font-semibold text-serena-text-muted">
                            Type
                        </label>
                        <select
                            v-model="costLineForm.cost_type"
                            class="mt-1 w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                        >
                            <option v-for="type in costTypeOptions" :key="type.value" :value="type.value">
                                {{ type.label }}
                            </option>
                        </select>
                        <p v-if="costLineErrors.cost_type" class="mt-1 text-xs text-serena-danger">
                            {{ costLineErrors.cost_type }}
                        </p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-serena-text-muted">
                            Libellé
                        </label>
                        <input
                            v-model="costLineForm.label"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                        />
                        <p v-if="costLineErrors.label" class="mt-1 text-xs text-serena-danger">
                            {{ costLineErrors.label }}
                        </p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold text-serena-text-muted">
                                Quantité
                            </label>
                            <input
                                v-model.number="costLineForm.quantity"
                                type="number"
                                step="0.01"
                                min="0"
                                class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                            />
                            <p v-if="costLineErrors.quantity" class="mt-1 text-xs text-serena-danger">
                                {{ costLineErrors.quantity }}
                            </p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-serena-text-muted">
                                Prix unitaire estimé
                            </label>
                            <input
                                v-model.number="costLineForm.unit_price"
                                type="number"
                                step="0.01"
                                min="0"
                                class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                            />
                            <p v-if="costLineErrors.unit_price" class="mt-1 text-xs text-serena-danger">
                                {{ costLineErrors.unit_price }}
                            </p>
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-serena-text-muted">
                            Devise
                        </label>
                        <input
                            v-model="costLineForm.currency"
                            type="text"
                            maxlength="3"
                            class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 text-sm uppercase text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                        />
                        <p v-if="costLineErrors.currency" class="mt-1 text-xs text-serena-danger">
                            {{ costLineErrors.currency }}
                        </p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-serena-text-muted">
                            Notes
                        </label>
                        <textarea
                            v-model="costLineForm.notes"
                            rows="2"
                            class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                        ></textarea>
                        <p v-if="costLineErrors.notes" class="mt-1 text-xs text-serena-danger">
                            {{ costLineErrors.notes }}
                        </p>
                    </div>
                    <div class="text-xs text-serena-text-muted">
                        Total estimé :
                        <span class="font-semibold text-serena-text-main">
                            {{ formatAmount(costLineTotalPreview, costLineForm.currency) }}
                        </span>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        class="text-sm text-serena-text-muted transition hover:text-serena-text-main"
                        @click="closeCostLineModal"
                    >
                        Annuler
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-serena-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-serena-primary-dark disabled:opacity-60"
                        :disabled="costLineSubmitting"
                        @click="saveCostLine"
                    >
                        {{ costLineSubmitting ? 'Enregistrement...' : 'Enregistrer' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import axios from 'axios';
import Swal from 'sweetalert2';
import Multiselect from 'vue-multiselect';
import AppLayout from '@/layouts/AppLayout.vue';

const COST_TYPES = [
    { value: 'labor', label: 'Main d oeuvre' },
    { value: 'parts', label: 'Pieces' },
    { value: 'transport', label: 'Transport' },
    { value: 'service', label: 'Service' },
    { value: 'other', label: 'Autre' },
];

export default {
    name: 'MaintenanceIndex',
    components: {
        AppLayout,
        Multiselect,
    },
    props: {
        tickets: {
            type: Object,
            required: true,
        },
        interventions: {
            type: Object,
            required: true,
        },
        filters: {
            type: Object,
            required: true,
        },
        statusOptions: {
            type: Array,
            required: true,
        },
        interventionStatusOptions: {
            type: Array,
            required: true,
        },
        assignableUsers: {
            type: Array,
            default: () => [],
        },
        maintenanceTypes: {
            type: Array,
            default: () => [],
        },
        rooms: {
            type: Array,
            default: () => [],
        },
        technicians: {
            type: Array,
            default: () => [],
        },
        openTickets: {
            type: Array,
            default: () => [],
        },
        activeTab: {
            type: String,
            default: 'tickets',
        },
        permissions: {
            type: Object,
            default: () => ({
                canUpdateStatus: false,
                canAssign: false,
                canClose: false,
            }),
        },
    },
    data() {
        return {
            activeTabValue: this.activeTab,
            ticketFilters: {
                status: this.filters?.status ?? 'open',
                room_id: this.filters?.room_id ?? '',
                maintenance_type: this.resolveMaintenanceType(this.filters?.maintenance_type_id),
                severity: this.filters?.severity ?? '',
                blocks_sale: this.normalizeBlocksSale(this.filters?.blocks_sale),
            },
            interventionFilters: {
                status: this.filters?.intervention_status ?? 'all',
                technician_id: this.filters?.technician_id ?? '',
                from: this.filters?.intervention_from ?? '',
                to: this.filters?.intervention_to ?? '',
            },
            showTicketModal: false,
            ticketFormMode: 'create',
            ticketForm: this.blankTicketForm(),
            ticketErrors: {},
            ticketSubmitting: false,
            showInterventionModal: false,
            interventionForm: this.blankInterventionForm(),
            interventionErrors: {},
            interventionSubmitting: false,
            selectedInterventionTickets: [],
            interventionTicketDetails: {},
            interventionCosts: [],
            showCostLineModal: false,
            costLineForm: this.blankCostLineForm(),
            costLineErrors: {},
            costLineSubmitting: false,
        };
    },
    computed: {
        hasTickets() {
            return Array.isArray(this.tickets?.data) && this.tickets.data.length > 0;
        },
        hasInterventions() {
            return Array.isArray(this.interventions?.data) && this.interventions.data.length > 0;
        },
        permissionFlags() {
            return this.$page?.props?.auth?.can ?? {};
        },
        canCreateTicket() {
            return this.permissionFlags.maintenance_tickets_create ?? false;
        },
        canUpdateStatus() {
            return this.permissionFlags.maintenance_tickets_update ?? this.permissions.canUpdateStatus;
        },
        canCloseStatus() {
            return this.permissionFlags.maintenance_tickets_close ?? this.permissions.canClose;
        },
        canAssign() {
            return this.permissionFlags.maintenance_tickets_close ?? this.permissions.canAssign;
        },
        canOverrideBlocksSale() {
            const roles = this.$page?.props?.auth?.user?.roles || [];

            return roles.some((role) => ['owner', 'manager'].includes(role.name));
        },
        canCreateIntervention() {
            return this.permissionFlags.maintenance_interventions_create ?? false;
        },
        canUpdateIntervention() {
            return this.permissionFlags.maintenance_interventions_update ?? false;
        },
        canSubmitIntervention() {
            return this.permissionFlags.maintenance_interventions_submit ?? false;
        },
        canApproveIntervention() {
            return this.permissionFlags.maintenance_interventions_approve ?? false;
        },
        canRejectIntervention() {
            return this.permissionFlags.maintenance_interventions_reject ?? false;
        },
        canMarkPaidIntervention() {
            return this.permissionFlags.maintenance_interventions_mark_paid ?? false;
        },
        canViewCostLines() {
            return this.permissionFlags.maintenance_costs_view
                || this.permissionFlags.maintenance_costs_edit
                || this.permissionFlags.maintenance_interventions_costs_manage
                || false;
        },
        canEditCostLines() {
            return this.permissionFlags.maintenance_costs_edit
                || this.permissionFlags.maintenance_interventions_costs_manage
                || false;
        },
        canEditIntervention() {
            if (!this.interventionForm.id) {
                return this.canCreateIntervention;
            }

            return this.canUpdateIntervention && !this.isInterventionLocked;
        },
        isInterventionLocked() {
            return ['approved', 'paid'].includes(this.interventionForm.accounting_status);
        },
        roomOptions() {
            return Array.isArray(this.rooms) ? this.rooms : [];
        },
        maintenanceTypeOptions() {
            return Array.isArray(this.maintenanceTypes) ? this.maintenanceTypes : [];
        },
        technicianOptions() {
            return Array.isArray(this.technicians) ? this.technicians : [];
        },
        ticketSeverityOptions() {
            return [
                { value: 'low', label: 'Gravite basse' },
                { value: 'medium', label: 'Gravite moyenne' },
                { value: 'high', label: 'Gravite haute' },
                { value: 'critical', label: 'Gravite critique' },
            ];
        },
        costTypeOptions() {
            return COST_TYPES;
        },
        ticketModalTitle() {
            return this.ticketFormMode === 'edit' ? 'Ticket de maintenance' : 'Signaler une panne';
        },
        selectedTicket() {
            return this.ticketFormMode === 'edit' ? this.ticketForm.originalTicket : null;
        },
        ticketOptions() {
            const base = Array.isArray(this.openTickets) ? [...this.openTickets] : [];

            this.selectedInterventionTickets.forEach((ticket) => {
                if (!base.find((item) => item.id === ticket.id)) {
                    base.push(ticket);
                }
            });

            return base;
        },
        interventionComputedTotal() {
            if (this.interventionCosts.length) {
                return this.interventionCosts.reduce((sum, line) => sum + Number(line.total_amount || 0), 0);
            }

            return Number(this.interventionForm.estimated_total_amount || this.interventionForm.total_cost || 0);
        },
        costLineTotalPreview() {
            const quantity = Number(this.costLineForm.quantity || 0);
            const unitPrice = Number(this.costLineForm.unit_price || 0);

            return quantity * unitPrice;
        },
    },
    watch: {
        filters: {
            deep: true,
            handler(newFilters) {
                this.ticketFilters.status = newFilters?.status ?? 'open';
                this.ticketFilters.room_id = newFilters?.room_id ?? '';
                this.ticketFilters.maintenance_type = this.resolveMaintenanceType(newFilters?.maintenance_type_id);
                this.ticketFilters.severity = newFilters?.severity ?? '';
                this.ticketFilters.blocks_sale = this.normalizeBlocksSale(newFilters?.blocks_sale);
                this.interventionFilters.status = newFilters?.intervention_status ?? 'all';
                this.interventionFilters.technician_id = newFilters?.technician_id ?? '';
                this.interventionFilters.from = newFilters?.intervention_from ?? '';
                this.interventionFilters.to = newFilters?.intervention_to ?? '';
            },
        },
        activeTab(newTab) {
            this.activeTabValue = newTab;
        },
        selectedInterventionTickets: {
            deep: true,
            handler(tickets) {
                this.syncInterventionTicketDetails(tickets);
            },
        },
    },
    methods: {
        normalizeBlocksSale(value) {
            if (value === null || value === undefined || value === '') {
                return '';
            }

            return value ? '1' : '0';
        },
        resolveMaintenanceType(typeId) {
            if (!typeId) {
                return null;
            }

            const types = Array.isArray(this.maintenanceTypes) ? this.maintenanceTypes : [];

            return types.find((type) => Number(type.id) === Number(typeId)) ?? null;
        },
        blankTicketForm() {
            return {
                id: null,
                room_id: '',
                title: '',
                maintenance_type: null,
                severity: 'medium',
                blocks_sale: false,
                description: '',
                status: 'open',
                assigned_to_user_id: null,
                originalTicket: null,
            };
        },
        blankInterventionForm() {
            return {
                id: null,
                technician: null,
                started_at: '',
                ended_at: '',
                summary: '',
                accounting_status: 'draft',
                currency: this.defaultCurrency(),
                total_cost: 0,
                estimated_total_amount: 0,
            };
        },
        blankCostLineForm() {
            return {
                id: null,
                cost_type: 'labor',
                label: '',
                quantity: 1,
                unit_price: 0,
                currency: this.defaultCurrency(),
                notes: '',
            };
        },
        defaultCurrency() {
            return this.$page?.props?.auth?.activeHotel?.currency ?? 'XAF';
        },
        switchTab(tab) {
            if (this.activeTabValue === tab) {
                return;
            }

            this.activeTabValue = tab;
            this.applyFilters();
        },
        tabButtonClasses(tab) {
            return this.activeTabValue === tab
                ? 'bg-serena-primary text-white'
                : 'bg-white text-serena-text-muted border border-serena-border hover:text-serena-primary';
        },
        statusFilterLabel(option) {
            switch (option) {
                case 'in_progress':
                    return 'En cours';
                case 'resolved':
                    return 'Resolus';
                case 'closed':
                    return 'Clotures';
                case 'all':
                    return 'Tous';
                case 'open':
                default:
                    return 'Ouverts';
            }
        },
        statusLabel(status) {
            switch (status) {
                case 'in_progress':
                    return 'En cours';
                case 'resolved':
                    return 'Resolus';
                case 'closed':
                    return 'Clotures';
                case 'open':
                default:
                    return 'Ouvert';
            }
        },
        severityLabel(severity) {
            switch (severity) {
                case 'critical':
                    return 'Critique';
                case 'high':
                    return 'Elevee';
                case 'medium':
                    return 'Moyenne';
                case 'low':
                default:
                    return 'Basse';
            }
        },
        statusButtonClasses(option) {
            const isActive = this.ticketFilters.status === option;

            return isActive
                ? 'bg-serena-primary text-white'
                : 'bg-white text-serena-text-muted border border-serena-border hover:text-serena-primary';
        },
        statusClasses(status) {
            switch (status) {
                case 'in_progress':
                    return 'bg-blue-100 text-blue-700 border border-blue-200';
                case 'resolved':
                    return 'bg-green-100 text-green-700 border border-green-200';
                case 'closed':
                    return 'bg-gray-200 text-gray-700 border border-gray-300';
                case 'open':
                default:
                    return 'bg-amber-100 text-amber-800 border border-amber-200';
            }
        },
        severityClasses(severity) {
            switch (severity) {
                case 'critical':
                    return 'bg-rose-100 text-rose-700 border border-rose-200';
                case 'high':
                    return 'bg-red-100 text-red-700 border border-red-200';
                case 'medium':
                    return 'bg-orange-100 text-orange-700 border border-orange-200';
                case 'low':
                default:
                    return 'bg-gray-100 text-gray-700 border border-gray-200';
            }
        },
        interventionTitle(intervention) {
            if (intervention?.technician?.name) {
                return `Intervention - ${intervention.technician.name}`;
            }

            return 'Intervention';
        },
        interventionStatusLabel(status) {
            switch (status) {
                case 'all':
                    return 'Toutes';
                case 'submitted':
                    return 'Soumise';
                case 'approved':
                    return 'Approuvee';
                case 'rejected':
                    return 'Rejetee';
                case 'paid':
                    return 'Payee';
                case 'draft':
                default:
                    return 'Brouillon';
            }
        },
        interventionStatusButtonClasses(option) {
            const isActive = this.interventionFilters.status === option;

            return isActive
                ? 'bg-serena-primary text-white'
                : 'bg-white text-serena-text-muted border border-serena-border hover:text-serena-primary';
        },
        interventionStatusClasses(status) {
            switch (status) {
                case 'submitted':
                    return 'bg-blue-50 text-blue-700 border border-blue-200';
                case 'approved':
                    return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
                case 'rejected':
                    return 'bg-rose-50 text-rose-700 border border-rose-200';
                case 'paid':
                    return 'bg-purple-50 text-purple-700 border-purple-200';
                case 'draft':
                default:
                    return 'bg-gray-100 text-gray-600 border border-gray-200';
            }
        },
        costTypeLabel(value) {
            return COST_TYPES.find((type) => type.value === value)?.label ?? value;
        },
        changeStatusFilter(option) {
            this.ticketFilters.status = option;
            this.applyFilters();
        },
        changeInterventionStatusFilter(option) {
            this.interventionFilters.status = option;
            this.applyFilters();
        },
        setValidationFilter() {
            this.interventionFilters.status = 'submitted';
            this.applyFilters();
        },
        applyFilters() {
            const params = this.buildQueryParams();

            this.$inertia.get(
                '/maintenance',
                params,
                {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                },
            );
        },
        buildQueryParams() {
            const params = {
                tab: this.activeTabValue,
                status: this.ticketFilters.status,
                room_id: this.ticketFilters.room_id || null,
                maintenance_type_id: this.ticketFilters.maintenance_type?.id ?? null,
                severity: this.ticketFilters.severity || null,
                blocks_sale: this.ticketFilters.blocks_sale === '' ? null : this.ticketFilters.blocks_sale,
                intervention_status: this.interventionFilters.status || null,
                technician_id: this.interventionFilters.technician_id || null,
                intervention_from: this.interventionFilters.from || null,
                intervention_to: this.interventionFilters.to || null,
            };

            return Object.fromEntries(
                Object.entries(params).filter(([, value]) => value !== null && value !== ''),
            );
        },
        linkClasses(link) {
            return link.active
                ? 'bg-serena-primary text-white font-semibold'
                : 'bg-white text-serena-text-muted border border-serena-border hover:text-serena-primary transition disabled:opacity-60';
        },
        visitLink(url) {
            if (!url) {
                return;
            }

            this.$inertia.visit(url, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        },
        roomLabel(room) {
            return room ? `#${room.number} (Etage ${room.floor ?? '-'})` : '—';
        },
        formatDateTime(value) {
            if (!value) {
                return '—';
            }

            const date = new Date(value);

            if (Number.isNaN(date.getTime())) {
                return value;
            }

            return date.toLocaleString();
        },
        formatAmount(value, currency = 'XAF') {
            const amount = Number(value || 0);

            return `${amount.toFixed(0)} ${currency}`;
        },
        formatQuantity(value) {
            const quantity = Number(value || 0);

            return quantity % 1 === 0 ? quantity.toFixed(0) : quantity.toFixed(2);
        },
        showUnauthorizedAlert() {
            Swal.fire({
                icon: 'error',
                title: 'Action non autorisee',
                text: 'Vous ne disposez pas des droits suffisants.',
            });
        },
        openCreateTicketModal() {
            if (!this.canCreateTicket) {
                this.showUnauthorizedAlert();

                return;
            }

            this.ticketFormMode = 'create';
            this.ticketForm = this.blankTicketForm();
            this.ticketErrors = {};
            this.showTicketModal = true;
        },
        openTicket(ticket) {
            this.ticketFormMode = 'edit';
            this.ticketForm = {
                id: ticket.id,
                room_id: ticket.room?.id ?? '',
                title: ticket.title ?? '',
                maintenance_type: ticket.maintenance_type?.id
                    ? this.maintenanceTypeOptions.find((type) => type.id === ticket.maintenance_type.id) ?? ticket.maintenance_type
                    : null,
                severity: ticket.severity ?? 'medium',
                blocks_sale: ticket.blocks_sale ?? false,
                description: ticket.description ?? '',
                status: ticket.status ?? 'open',
                assigned_to_user_id: ticket.assigned_to?.id ?? null,
                originalTicket: ticket,
            };
            this.ticketErrors = {};
            this.showTicketModal = true;
        },
        closeTicketModal() {
            this.showTicketModal = false;
            this.ticketErrors = {};
            this.ticketForm = this.blankTicketForm();
        },
        async submitTicketForm() {
            if (this.ticketFormMode === 'create' && !this.canCreateTicket) {
                this.showUnauthorizedAlert();

                return;
            }

            if (this.ticketFormMode === 'edit' && !this.canUpdateStatus) {
                this.showUnauthorizedAlert();

                return;
            }

            this.ticketSubmitting = true;
            this.ticketErrors = {};

            const payload = {
                maintenance_type_id: this.ticketForm.maintenance_type?.id ?? null,
                severity: this.ticketForm.severity,
                description: this.ticketForm.description || null,
            };

            if (this.ticketFormMode === 'create') {
                payload.room_id = this.ticketForm.room_id;
                payload.title = this.ticketForm.title;
            } else if (this.canUpdateStatus) {
                const isClosing = ['resolved', 'closed'].includes(this.ticketForm.status);

                if (isClosing && !this.canCloseStatus) {
                    this.showUnauthorizedAlert();
                    this.ticketSubmitting = false;

                    return;
                }

                payload.status = this.ticketForm.status;
            }

            if (this.canAssign && this.ticketFormMode === 'edit') {
                payload.assigned_to_user_id = this.ticketForm.assigned_to_user_id;
            }

            if (this.canOverrideBlocksSale) {
                payload.blocks_sale = this.ticketForm.blocks_sale;
            }

            try {
                if (this.ticketFormMode === 'create') {
                    await axios.post('/maintenance-tickets', payload);
                } else {
                    await axios.patch(`/maintenance-tickets/${this.ticketForm.id}`, payload);
                }

                this.closeTicketModal();

                Swal.fire({
                    icon: 'success',
                    title: this.ticketFormMode === 'create' ? 'Ticket cree' : 'Ticket mis a jour',
                    timer: 1600,
                    showConfirmButton: false,
                });

                this.$inertia.reload({
                    preserveScroll: true,
                    only: ['tickets', 'openTickets'],
                });
            } catch (error) {
                if (error.response?.status === 403) {
                    this.showUnauthorizedAlert();
                } else if (error.response?.status === 422) {
                    this.ticketErrors = Object.fromEntries(
                        Object.entries(error.response.data.errors || {}).map(([key, value]) => [
                            key,
                            Array.isArray(value) ? value[0] : value,
                        ]),
                    );
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: error.response?.data?.message ?? 'Impossible de sauvegarder le ticket.',
                    });
                }
            } finally {
                this.ticketSubmitting = false;
            }
        },
        openCreateInterventionModal() {
            if (!this.canCreateIntervention) {
                this.showUnauthorizedAlert();

                return;
            }

            this.resetInterventionForm();
            this.showInterventionModal = true;
        },
        visitInterventionDetail(intervention) {
            if (!intervention?.id) {
                return;
            }

            this.$inertia.visit(`/maintenance/interventions/${intervention.id}`, {
                preserveScroll: true,
                preserveState: true,
            });
        },
        closeInterventionModal() {
            this.showInterventionModal = false;
            this.resetInterventionForm();
        },
        resetInterventionForm() {
            this.interventionForm = this.blankInterventionForm();
            this.interventionErrors = {};
            this.selectedInterventionTickets = [];
            this.interventionTicketDetails = {};
            this.interventionCosts = [];
        },
        applyInterventionResponse(intervention) {
            if (!intervention) {
                return;
            }

            this.interventionForm = {
                id: intervention.id,
                technician: intervention.technician?.id
                    ? this.technicianOptions.find((tech) => tech.id === intervention.technician.id) ?? intervention.technician
                    : null,
                started_at: intervention.started_at ? this.toDateTimeLocal(intervention.started_at) : '',
                ended_at: intervention.ended_at ? this.toDateTimeLocal(intervention.ended_at) : '',
                summary: intervention.summary ?? '',
                accounting_status: intervention.accounting_status ?? 'draft',
                currency: intervention.currency ?? this.defaultCurrency(),
                total_cost: intervention.total_cost ?? 0,
                estimated_total_amount: intervention.estimated_total_amount ?? intervention.total_cost ?? 0,
            };

            this.interventionCosts = Array.isArray(intervention.costs) ? intervention.costs : [];

            if (Array.isArray(intervention.tickets)) {
                this.selectedInterventionTickets = intervention.tickets;
                const details = {};

                intervention.tickets.forEach((ticket) => {
                    details[ticket.id] = {
                        work_done: ticket.work_done ?? '',
                        labor_cost: Number(ticket.labor_cost || 0),
                        parts_cost: Number(ticket.parts_cost || 0),
                    };
                });

                this.interventionTicketDetails = details;
            }
        },
        syncInterventionTicketDetails(tickets) {
            const details = { ...this.interventionTicketDetails };

            (tickets || []).forEach((ticket) => {
                if (!details[ticket.id]) {
                    details[ticket.id] = {
                        work_done: '',
                        labor_cost: 0,
                        parts_cost: 0,
                    };
                }
            });

            Object.keys(details).forEach((key) => {
                if (!tickets.find((ticket) => String(ticket.id) === String(key))) {
                    delete details[key];
                }
            });

            this.interventionTicketDetails = details;
        },
        buildInterventionPayload() {
            return {
                technician_id: this.interventionForm.technician?.id ?? null,
                started_at: this.interventionForm.started_at || null,
                ended_at: this.interventionForm.ended_at || null,
                summary: this.interventionForm.summary || null,
                currency: this.interventionForm.currency || null,
                tickets: this.selectedInterventionTickets.map((ticket) => ({
                    maintenance_ticket_id: ticket.id,
                    work_done: this.interventionTicketDetails[ticket.id]?.work_done || null,
                    labor_cost: Number(this.interventionTicketDetails[ticket.id]?.labor_cost || 0),
                    parts_cost: Number(this.interventionTicketDetails[ticket.id]?.parts_cost || 0),
                })),
            };
        },
        async saveIntervention() {
            if (!this.canEditIntervention) {
                this.showUnauthorizedAlert();

                return;
            }

            this.interventionSubmitting = true;
            this.interventionErrors = {};
            const isNew = !this.interventionForm.id;

            const payload = this.buildInterventionPayload();

            try {
                const response = this.interventionForm.id
                    ? await axios.put(`/maintenance/interventions/${this.interventionForm.id}`, payload)
                    : await axios.post('/maintenance/interventions', payload);
                const intervention = response.data?.intervention ?? null;

                this.applyInterventionResponse(intervention);

                Swal.fire({
                    icon: 'success',
                    title: isNew ? 'Intervention creee' : 'Intervention enregistree',
                    timer: 1500,
                    showConfirmButton: false,
                });

                this.$inertia.reload({
                    preserveScroll: true,
                    only: ['interventions', 'openTickets'],
                });
            } catch (error) {
                if (error?.response?.status === 403) {
                    this.showUnauthorizedAlert();
                } else if (error.response?.status === 422) {
                    this.interventionErrors = Object.fromEntries(
                        Object.entries(error.response.data.errors || {}).map(([key, value]) => [
                            key,
                            Array.isArray(value) ? value[0] : value,
                        ]),
                    );
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: error.response?.data?.message ?? "Impossible de sauvegarder l'intervention.",
                    });
                }
            } finally {
                this.interventionSubmitting = false;
            }
        },
        async submitIntervention() {
            if (!this.canSubmitIntervention) {
                this.showUnauthorizedAlert();

                return;
            }

            if (!this.interventionForm.id) {
                await this.saveIntervention();
            }

            if (!this.interventionForm.id) {
                return;
            }

            this.interventionSubmitting = true;

            try {
                const response = await axios.post(`/maintenance/interventions/${this.interventionForm.id}/submit`);
                this.applyInterventionResponse(response.data?.intervention ?? null);

                Swal.fire({
                    icon: 'success',
                    title: 'Intervention soumise',
                    timer: 1500,
                    showConfirmButton: false,
                });

                this.$inertia.reload({
                    preserveScroll: true,
                    only: ['interventions'],
                });
            } catch (error) {
                if (error?.response?.status === 403) {
                    this.showUnauthorizedAlert();
                } else if (error.response?.status === 422) {
                    this.interventionErrors = Object.fromEntries(
                        Object.entries(error.response.data.errors || {}).map(([key, value]) => [
                            key,
                            Array.isArray(value) ? value[0] : value,
                        ]),
                    );
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: error.response?.data?.message ?? "Impossible de soumettre l'intervention.",
                    });
                }
            } finally {
                this.interventionSubmitting = false;
            }
        },
        async approveIntervention(intervention) {
            if (!this.canApproveIntervention || !intervention?.id) {
                this.showUnauthorizedAlert();

                return;
            }

            this.interventionSubmitting = true;

            try {
                const response = await axios.post(`/maintenance/interventions/${intervention.id}/approve`);
                const updated = response.data?.intervention ?? null;

                if (this.interventionForm.id === intervention.id) {
                    this.applyInterventionResponse(updated);
                }

                this.$inertia.reload({
                    preserveScroll: true,
                    only: ['interventions'],
                });
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: error.response?.data?.message ?? "Impossible d'approuver l'intervention.",
                });
            } finally {
                this.interventionSubmitting = false;
            }
        },
        async promptRejectIntervention(intervention) {
            if (!this.canRejectIntervention || !intervention?.id) {
                this.showUnauthorizedAlert();

                return;
            }

            const result = await Swal.fire({
                icon: 'warning',
                title: 'Rejeter l intervention',
                input: 'textarea',
                inputLabel: 'Raison du rejet',
                inputPlaceholder: 'Expliquez la raison du rejet...',
                showCancelButton: true,
                confirmButtonText: 'Rejeter',
                cancelButtonText: 'Annuler',
            });

            if (!result.isConfirmed) {
                return;
            }

            const rejectionReason = result.value || '';

            this.interventionSubmitting = true;

            try {
                const response = await axios.post(`/maintenance/interventions/${intervention.id}/reject`, {
                    rejection_reason: rejectionReason,
                });
                const updated = response.data?.intervention ?? null;

                if (this.interventionForm.id === intervention.id) {
                    this.applyInterventionResponse(updated);
                }

                this.$inertia.reload({
                    preserveScroll: true,
                    only: ['interventions'],
                });
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: error.response?.data?.message ?? "Impossible de rejeter l'intervention.",
                });
            } finally {
                this.interventionSubmitting = false;
            }
        },
        async markPaidIntervention(intervention) {
            if (!this.canMarkPaidIntervention || !intervention?.id) {
                this.showUnauthorizedAlert();

                return;
            }

            this.interventionSubmitting = true;

            try {
                const response = await axios.post(`/maintenance/interventions/${intervention.id}/mark-paid`);
                const updated = response.data?.intervention ?? null;

                if (this.interventionForm.id === intervention.id) {
                    this.applyInterventionResponse(updated);
                }

                this.$inertia.reload({
                    preserveScroll: true,
                    only: ['interventions'],
                });
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: error.response?.data?.message ?? 'Impossible de marquer paye.',
                });
            } finally {
                this.interventionSubmitting = false;
            }
        },
        openCostLineModal(line = null) {
            if (!this.interventionForm.id) {
                Swal.fire({
                    icon: 'info',
                    title: 'Sauvegardez l intervention',
                    text: 'Enregistrez d abord l intervention avant d ajouter des couts.',
                });

                return;
            }

            if (!this.canEditCostLines) {
                this.showUnauthorizedAlert();

                return;
            }

            this.costLineForm = line
                ? {
                    id: line.id,
                    cost_type: line.cost_type,
                    label: line.label,
                    quantity: line.quantity,
                    unit_price: line.unit_price,
                    currency: line.currency ?? this.interventionForm.currency,
                    notes: line.notes ?? '',
                }
                : {
                    ...this.blankCostLineForm(),
                    currency: this.interventionForm.currency || this.defaultCurrency(),
                };
            this.costLineErrors = {};
            this.showCostLineModal = true;
        },
        closeCostLineModal() {
            this.showCostLineModal = false;
            this.costLineForm = this.blankCostLineForm();
            this.costLineErrors = {};
        },
        async saveCostLine() {
            if (!this.interventionForm.id) {
                return;
            }

            this.costLineSubmitting = true;
            this.costLineErrors = {};

            const payload = {
                cost_type: this.costLineForm.cost_type,
                label: this.costLineForm.label,
                quantity: this.costLineForm.quantity,
                unit_price: this.costLineForm.unit_price,
                currency: this.costLineForm.currency,
                notes: this.costLineForm.notes || null,
            };

            try {
                const response = this.costLineForm.id
                    ? await axios.put(`/maintenance/interventions/${this.interventionForm.id}/cost-lines/${this.costLineForm.id}`, payload)
                    : await axios.post(`/maintenance/interventions/${this.interventionForm.id}/cost-lines`, payload);
                const intervention = response.data?.intervention ?? null;

                this.applyInterventionResponse(intervention);
                this.closeCostLineModal();
            } catch (error) {
                if (error?.response?.status === 403) {
                    this.showUnauthorizedAlert();
                } else if (error.response?.status === 422) {
                    this.costLineErrors = Object.fromEntries(
                        Object.entries(error.response.data.errors || {}).map(([key, value]) => [
                            key,
                            Array.isArray(value) ? value[0] : value,
                        ]),
                    );
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: error.response?.data?.message ?? 'Impossible de sauvegarder la ligne.',
                    });
                }
            } finally {
                this.costLineSubmitting = false;
            }
        },
        async deleteCostLine(line) {
            if (!this.interventionForm.id || !line?.id) {
                return;
            }

            const result = await Swal.fire({
                icon: 'warning',
                title: 'Supprimer la ligne ? ',
                text: 'Cette action est definitive.',
                showCancelButton: true,
                confirmButtonText: 'Supprimer',
                cancelButtonText: 'Annuler',
            });

            if (!result.isConfirmed) {
                return;
            }

            try {
                const response = await axios.delete(`/maintenance/interventions/${this.interventionForm.id}/cost-lines/${line.id}`);
                const intervention = response.data?.intervention ?? null;

                this.applyInterventionResponse(intervention);
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: error.response?.data?.message ?? 'Impossible de supprimer la ligne.',
                });
            }
        },
        ticketOptionLabel(option) {
            if (!option) {
                return '';
            }

            const roomLabel = option.room_number ? `Ch ${option.room_number}` : 'Chambre';
            const typeLabel = option.maintenance_type?.name ?? 'Autre';

            return `${roomLabel} - ${typeLabel} - ${option.title ?? 'Ticket'}`;
        },
        toDateTimeLocal(value) {
            if (!value) {
                return '';
            }

            const date = new Date(value);
            if (Number.isNaN(date.getTime())) {
                return '';
            }

            const pad = (num) => String(num).padStart(2, '0');

            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
        },
    },
};
</script>
