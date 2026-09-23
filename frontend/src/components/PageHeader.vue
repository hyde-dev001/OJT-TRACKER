<script setup>
defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
})
</script>

<template>
  <header class="flex flex-wrap items-start justify-between gap-5">
    <div>
      <h1 class="text-3xl font-semibold tracking-tight text-slate-950">{{ title }}</h1>
      <p v-if="description" class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">{{ description }}</p>
    </div>
    <div v-if="$slots.action" class="shrink-0">
      <slot name="action" />
    </div>
  </header>
</template>
