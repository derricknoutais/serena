<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <input
    type="text"
    data-slot="sidebar-input"
    data-sidebar="input"
    :class="cn(
      'h-8 w-full rounded-lg border border-serena-border bg-white px-3 text-sm text-serena-text-main outline-none transition placeholder:text-serena-text-muted/70 focus:border-serena-primary focus:ring-2 focus:ring-serena-primary-soft',
      props.class,
    )"
  />
</template>
