<template>
    <div class="p-6">
        <h1 class="mb-3 text-xl font-bold">Détails de la réservation</h1>
        <p>
            <strong>Code :</strong>
            {{ reservation.code }}
        </p>
        <p>
            <strong>Client :</strong>
            {{ reservation.guest?.name }}
        </p>
        <p>
            <strong>Statut :</strong>
            {{ reservation.status }}
        </p>
        <p>
            <strong>Arrivée :</strong>
            {{ reservation.check_in_date }}
        </p>
        <p>
            <strong>Départ :</strong>
            {{ reservation.check_out_date }}
        </p>
        <p>
            <strong>Total :</strong>
            {{ reservation.total_amount }}
            {{ reservation.currency }}
        </p>
    </div>
</template>

<script>
export default {
    props: {
        reservation: {
            type: Object,
            required: true,
        },
    },
};
</script>

