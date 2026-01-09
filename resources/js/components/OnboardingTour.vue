<template>
    <div v-if="isOwner && showButton" class="onboarding-layer">
        <button
            type="button"
            class="fixed bottom-4 right-4 z-30 inline-flex items-center rounded-full bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-lg transition hover:bg-indigo-700"
            @click="startTour"
        >
            Démarrer l'onboarding
        </button>

        <VOnboardingWrapper
            v-if="showTour"
            ref="wrapper"
            v-model:current-step="currentStep"
            :steps="steps"
            :options="{ overlay: { padding: 12 } }"
            @finish="() => stopTour(true)"
            @skip="() => stopTour(true)"
        >
            <template #default="slotProps">
                <div class="w-80 rounded-lg bg-white p-4 text-sm text-gray-800 shadow-xl">
                    <p class="text-xs font-semibold text-gray-500">
                        Étape {{ getStepNumber(currentStep) }} / {{ steps.length }}
                    </p>
                    <h3 class="mt-1 text-base font-semibold text-gray-900">
                        {{ stepAt(currentStep)?.content?.title ?? '' }}
                    </h3>
                    <p class="mt-2 text-sm text-gray-700">
                        {{ stepAt(currentStep)?.content?.description ?? '' }}
                    </p>
                    <div v-if="stepAt(currentStep)?.content?.actionHref" class="mt-3">
                        <button
                            type="button"
                            class="inline-flex items-center rounded-md bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100"
                            @click="handleAction(currentStep, stepAt(currentStep)?.content?.actionHref)"
                        >
                            {{ stepAt(currentStep)?.content?.actionLabel || 'Ouvrir' }}
                        </button>
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <button
                            type="button"
                            class="text-xs font-semibold text-gray-500 hover:text-gray-700"
                            :disabled="currentStep <= 0"
                            @click="handlePrev(slotProps)"
                        >
                            Précédent
                        </button>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="rounded-md bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-200"
                                @click="stopTour(false)"
                            >
                                Fermer
                            </button>
                            <button
                                type="button"
                                class="rounded-md bg-indigo-600 px-3 py-1 text-xs font-semibold text-white hover:bg-indigo-700"
                                @click="slotProps.isLast ? stopTour(true) : handleNext(slotProps)"
                            >
                                {{ slotProps.isLast ? 'Terminer' : 'Suivant' }}
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </VOnboardingWrapper>
    </div>
</template>

<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { VOnboardingWrapper, type Step } from 'v-onboarding';
import 'v-onboarding/dist/style.css';

defineProps<{
    isOwner: boolean;
}>();

const showTour = ref(false);
const showButton = ref(true);
const wrapper = ref<InstanceType<typeof VOnboardingWrapper> | null>(null);
const currentStep = ref(0);
const page = usePage();
const STORAGE_KEY = 'onboarding_step';
const HIDE_KEY = 'onboarding_hide_button';

const rawSteps: Step[] = [
    {
        content: {
            number: 1,
            title: 'Créer votre hôtel',
            description: 'Commencez par renseigner les informations principales de l’hôtel.',
            actionHref: '/settings/resources/hotel',
            actionLabel: 'Ouvrir Hôtel',
        },
    },
    {
        content: {
            number: 2,
            title: 'Inviter votre équipe',
            description: 'Invitez vos collaborateurs et assignez-leur des rôles pour cet hôtel.',
            actionHref: '/settings/resources/guests',
            actionLabel: 'Inviter / assigner',
        },
    },
    {
        content: {
            number: 3,
            title: 'Configurer les types de chambres',
            description: 'Ajoutez vos catégories de chambres (Standard, Suite, etc.).',
            actionHref: '/settings/resources/room-types',
            actionLabel: 'Types de chambres',
        },
    },
    {
        content: {
            number: 4,
            title: 'Créer les chambres',
            description: 'Enregistrez les chambres physiques en les liant à un type.',
            actionHref: '/settings/resources/rooms',
            actionLabel: 'Chambres',
        },
    },
    {
        content: {
            number: 5,
            title: 'Définir vos offres',
            description: 'Ajoutez les offres (nuitée, forfait, détente…) et leurs tarifs.',
            actionHref: '/settings/resources/offers',
            actionLabel: 'Offres',
        },
    },
];

const steps = computed<Step[]>(() =>
    rawSteps.map((step) => ({
        attachTo: step.attachTo ?? { element: 'body' },
        ...step,
    })),
);

const stepAt = (idx: number): Step | undefined => steps.value[idx];

const getStepNumber = (index: number): number => {
    return rawSteps[index]?.content?.number ?? index + 1;
};

const getStoredStepNumber = (): number => {
    const stored = localStorage.getItem(STORAGE_KEY);
    const parsed = stored ? Number.parseInt(stored, 10) : NaN;
    if (Number.isNaN(parsed) || parsed < 1) {
        return 1;
    }
    return parsed;
};

const startTour = (): void => {
    const stepNumber = getStoredStepNumber();
    console.log('Starting tour at step number:', stepNumber);
    const idx = Math.max(0, Math.min(rawSteps.length - 1, stepNumber - 1));
    console.log('Resolved step index:', idx);
    currentStep.value = idx;
    localStorage.setItem(STORAGE_KEY, String(stepNumber));
    showTour.value = true;
    console.log('Showing tour...');
    requestAnimationFrame(() => {
        wrapper.value?.start?.(idx);
    });
};

const handleNext = (slotProps: any): void => {
    slotProps?.next?.();
    const nextIndex = Math.min(currentStep.value + 1, rawSteps.length - 1);
    currentStep.value = nextIndex;
    localStorage.setItem(STORAGE_KEY, String(getStepNumber(nextIndex)));
};

const handlePrev = (slotProps: any): void => {
    const prevIndex = Math.max(0, currentStep.value - 1);
    currentStep.value = prevIndex;
    localStorage.setItem(STORAGE_KEY, String(getStepNumber(prevIndex)));
    slotProps?.previous?.();
};

const stopTour = (complete: boolean, preserveStep = false): void => {
    showTour.value = false;
    const number = rawSteps[currentStep.value]?.content?.number ?? currentStep.value + 1;

    if (! preserveStep) {
        localStorage.setItem(STORAGE_KEY, String(number));
    }

    if (complete) {
        localStorage.setItem(HIDE_KEY, '1');
        showButton.value = false;
    } else {
        showButton.value = true;
    }
};

const handleAction = (stepIndex: number, href?: string): void => {
    if (!href) {
        return;
    }
    const nextNumber = getStepNumber(stepIndex + 1);
    localStorage.setItem(STORAGE_KEY, String(nextNumber));
    stopTour(false, true);
    window.open(href, '_blank', 'noopener');
};

const resumeFromStorage = (): void => {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored === null) {
        return;
    }
    const index = Number.parseInt(stored, 10);
    if (Number.isNaN(index)) {
        localStorage.removeItem(STORAGE_KEY);
        return;
    }
    const idx = Math.max(0, Math.min(rawSteps.length - 1, index - 1));
    currentStep.value = idx;
    showTour.value = true;
    requestAnimationFrame(() => {
        wrapper.value?.start?.(idx);
    });
};

watch(
    () => page?.url ?? page.value?.url,
    () => {
        if (showTour.value) {
            stopTour(false);
        }
    },
);

onBeforeUnmount(() => {
    stopTour(false);
});

onMounted(() => {
    if (localStorage.getItem(HIDE_KEY) === '1') {
        showButton.value = false;
    }
    resumeFromStorage();
});
</script>

<style scoped>
.onboarding-layer {
    --v-onboarding-overlay-z: 30;
    --v-onboarding-step-z: 40;
}
</style>
