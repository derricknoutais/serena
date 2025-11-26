<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    centralDomain?: string;
    tenant?: string | null;
}>();

const form = useForm({
    tenant: props.tenant ?? '',
});

const preview = computed(() =>
    form.tenant
        ? `${form.tenant.replace(/\s+/g, '-').toLowerCase()}.${
              props.centralDomain ?? 'saas-template.test'
          }`
        : `your-team.${props.centralDomain ?? 'saas-template.test'}`,
);

const submit = () => {
    form.post('/login/tenant', {
        preserveScroll: true,
    });
};
</script>

<template>
    <AuthBase
        title="Retrouvez votre espace"
        description="Indiquez le domaine ou le slug de votre société pour continuer"
    >
        <Head title="Connexion" />

        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="tenant">Domaine de la société</Label>
                    <span class="text-xs text-muted-foreground">
                        ex. {{ preview }}
                    </span>
                </div>
                <Input
                    id="tenant"
                    v-model="form.tenant"
                    type="text"
                    name="tenant"
                    required
                    autofocus
                    autocomplete="organization"
                    placeholder="acme ou acme.saas-template.test"
                />
                <InputError :message="form.errors.tenant" />
            </div>

            <Button
                type="submit"
                class="w-full"
                :disabled="form.processing"
                data-test="tenant-login-redirect"
            >
                <Spinner v-if="form.processing" />
                Continuer vers la connexion
            </Button>
        </form>
    </AuthBase>
</template>
