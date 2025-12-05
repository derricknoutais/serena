<script lang="ts">
import { computed, defineComponent, type PropType } from 'vue';

export default defineComponent({
    name: 'PrimaryButton',
    inheritAttrs: false,
    props: {
        type: {
            type: String as PropType<'button' | 'submit' | 'reset'>,
            default: 'button',
        },
        disabled: {
            type: Boolean,
            default: false,
        },
        variant: {
            type: String as PropType<'primary' | 'danger'>,
            default: 'primary',
        },
    },
    setup(props, { attrs }) {
        const baseClass =
            'inline-flex cursor-pointer items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors duration-200 hover:brightness-105 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-serena-primary-soft focus-visible:ring-offset-2 focus-visible:ring-offset-serena-card disabled:cursor-not-allowed disabled:opacity-60';

        const buttonClass = computed(() => {
            const tone =
                props.variant === 'danger'
                    ? 'bg-serena-danger hover:bg-serena-danger/90 focus-visible:ring-serena-danger/40'
                    : 'bg-serena-primary hover:bg-serena-primary-dark';

            return [baseClass, tone, (attrs as any).class];
        });

        const otherAttrs = computed<Record<string, unknown>>(() => {
            const { class: _class, ...rest } = attrs as Record<string, unknown>;

            return rest;
        });

        return {
            buttonClass,
            otherAttrs,
        };
    },
});
</script>

<template>
    <button
        v-bind="otherAttrs"
        :type="type"
        :disabled="disabled"
        :class="buttonClass"
    >
        <slot />
    </button>
</template>
