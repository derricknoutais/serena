<script lang="ts">
import { computed, defineComponent, type PropType } from 'vue';

export default defineComponent({
    name: 'SecondaryButton',
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
    },
    setup(props, { attrs }) {
        const buttonClass = computed(() => [
            'inline-flex cursor-pointer items-center justify-center gap-2 rounded-full border border-serena-border bg-serena-primary-soft/60 px-4 py-2 text-sm font-medium text-serena-text-main shadow-sm transition-colors duration-200 hover:bg-serena-primary-soft hover:brightness-105 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-serena-primary-soft focus-visible:ring-offset-2 focus-visible:ring-offset-serena-card disabled:cursor-not-allowed disabled:opacity-60',
            (attrs as any).class,
        ]);

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
