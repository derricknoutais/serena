<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import { PanelLeft } from 'lucide-vue-next'
import { useSidebar } from './utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()

const { toggleSidebar } = useSidebar()
</script>

<template>
  <button
    type="button"
    data-sidebar="trigger"
    data-slot="sidebar-trigger"
    :class="cn('flex h-7 w-7 items-center justify-center rounded-lg border border-serena-border bg-white text-serena-text-main shadow-sm transition hover:bg-serena-primary-soft', props.class)"
    @click="toggleSidebar"
  >
    <PanelLeft class="h-4 w-4" />
    <span class="sr-only">Toggle Sidebar</span>
  </button>
</template>
