<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { defineOptions, defineProps } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <img src="/img/serena_logo.png" alt="" :class="className" />
</template>
