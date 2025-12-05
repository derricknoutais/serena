<script lang="ts">
import { defineComponent, type PropType } from 'vue';

export default defineComponent({
    name: 'SerenaCard',
    props: {
        as: {
            type: String as PropType<keyof HTMLElementTagNameMap>,
            default: 'div',
        },
    },
});
</script>

<template>
    <component
        :is="as"
        v-bind="$attrs"
        class="rounded-xl border border-serena-border/30 bg-serena-card p-4 shadow-sm lg:p-6"
    >
        <slot />
    </component>
</template>
