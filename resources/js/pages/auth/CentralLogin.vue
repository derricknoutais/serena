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
        title="Find your company"
        description="Enter your company domain or slug to continue"
    >
        <Head title="Log in" />

        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="tenant">Company domain</Label>
                    <span class="text-xs text-muted-foreground">
                        e.g. {{ preview }}
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
                    placeholder="acme or acme.saas-template.test"
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
                Continue to login
            </Button>
        </form>
    </AuthBase>
</template>
