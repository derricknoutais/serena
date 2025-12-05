<script lang="ts">
import {
    computed,
    defineComponent,
    ref,
    watch,
    type PropType,
    type Ref,
} from 'vue';

export default defineComponent({
    name: 'TextInput',
    inheritAttrs: false,
    props: {
        modelValue: {
            type: [String, Number] as PropType<string | number | undefined>,
            default: undefined,
        },
        defaultValue: {
            type: [String, Number] as PropType<string | number | undefined>,
            default: undefined,
        },
        label: {
            type: String,
            default: '',
        },
        id: {
            type: String,
            default: '',
        },
        type: {
            type: String,
            default: 'text',
        },
        disabled: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['update:modelValue', 'input', 'focus', 'blur'],
    setup(props, { emit, attrs, slots }) {
        const innerValue: Ref<string | number | undefined> = ref(
            props.modelValue ?? props.defaultValue ?? '',
        );

        watch(
            () => props.modelValue,
            (value) => {
                if (value !== undefined) {
                    innerValue.value = value;
                } else if (props.defaultValue !== undefined) {
                    innerValue.value = props.defaultValue;
                } else if (value === undefined && props.defaultValue === undefined) {
                    innerValue.value = '';
                }
            },
        );

        watch(
            () => props.defaultValue,
            (value) => {
                if (props.modelValue === undefined) {
                    innerValue.value = value ?? '';
                }
            },
        );

        const passThroughAttrs = computed<Record<string, unknown>>(() => {
            const { class: _class, ...rest } = attrs as Record<string, unknown>;

            return rest;
        });

        const handleInput = (event: Event) => {
            const target = event.target as HTMLInputElement;
            const value = target.value;

            innerValue.value = value;
            emit('update:modelValue', value);
            emit('input', event);
        };

        return {
            innerValue,
            passThroughAttrs,
            handleInput,
            slots,
            inputClass: computed(() => [
                'w-full rounded-lg border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main shadow-sm transition focus:border-serena-primary focus:ring-2 focus:ring-serena-primary-soft focus-visible:outline-none disabled:cursor-not-allowed disabled:bg-serena-bg-soft disabled:text-serena-text-muted',
                (attrs as any).class,
            ]),
        };
    },
});
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <div
            v-if="label || slots.label || slots['label-action']"
            class="flex items-center justify-between text-xs text-serena-text-muted"
        >
            <label
                v-if="label || slots.label"
                :for="id"
                class="font-medium"
            >
                <slot name="label">{{ label }}</slot>
            </label>
            <div v-if="slots['label-action']" class="text-serena-primary">
                <slot name="label-action" />
            </div>
        </div>

        <input
            v-bind="passThroughAttrs"
            :id="id"
            :type="type"
            v-model="innerValue"
            :disabled="disabled"
            :class="inputClass"
            @input="handleInput"
            @focus="$emit('focus', $event)"
            @blur="$emit('blur', $event)"
        />
    </div>
</template>
