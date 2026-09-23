<script setup>
defineProps({
  title: { type: String, required: true },
  message: { type: String, required: true },
})
</script>

<template>
  <div class="rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
    <h2 class="font-semibold text-slate-950">{{ title }}</h2>
    <p class="mt-2 text-sm text-slate-600">{{ message }}</p>
    <div v-if="$slots.action" class="mt-4">
      <slot name="action" />
    </div>
  </div>
</template>
