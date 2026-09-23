<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: { type: String, required: true },
})

const labels = {
  to_do: 'To Do',
  in_progress: 'In Progress',
  completed: 'Completed',
  incomplete: 'Incomplete',
  not_started: 'Not started',
  on_track: 'On track',
  at_risk: 'At risk',
  complete: 'Complete',
  deadline_passed: 'Deadline passed',
  pace_unavailable: 'Pace unavailable',
  ready: 'Ready',
  not_ready: 'Not ready',
}

const label = computed(() => labels[props.status] ?? props.status.replaceAll('_', ' '))
const tone = computed(() => {
  if (['completed', 'complete', 'on_track', 'ready'].includes(props.status)) return 'bg-emerald-50 text-emerald-700 ring-emerald-200'
  if (['in_progress', 'at_risk', 'not_ready'].includes(props.status)) return 'bg-amber-50 text-amber-700 ring-amber-200'
  if (['deadline_passed'].includes(props.status)) return 'bg-red-50 text-red-700 ring-red-200'
  return 'bg-slate-100 text-slate-700 ring-slate-200'
})
</script>

<template>
  <span :class="['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize ring-1', tone]" role="status">
    {{ label }}
  </span>
</template>
