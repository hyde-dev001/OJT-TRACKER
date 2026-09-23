<script setup>
import { computed } from 'vue'

const props = defineProps({
  percentage: { type: Number, default: 0 },
  completedLabel: { type: String, required: true },
  requiredLabel: { type: String, required: true },
  remainingLabel: { type: String, default: '' },
})

const width = computed(() => Math.min(100, Math.max(0, Number(props.percentage) || 0)))
</script>

<template>
  <div>
    <div class="flex flex-wrap items-baseline justify-between gap-2 text-sm">
      <span class="font-semibold text-slate-950">{{ completedLabel }}</span>
      <span class="text-slate-600">{{ requiredLabel }}</span>
    </div>
    <div class="progress-bar__track mt-3 h-2 overflow-hidden rounded-full bg-slate-200" role="progressbar" :aria-valuenow="width" aria-valuemin="0" aria-valuemax="100" :aria-label="`${width}% of required hours completed`">
      <div class="progress-bar__fill h-full rounded-full bg-slate-950 motion-safe:transition-[width]" :style="{ width: `${width}%` }" />
    </div>
    <p v-if="remainingLabel" class="mt-2 text-sm text-slate-600">{{ remainingLabel }}</p>
  </div>
</template>
