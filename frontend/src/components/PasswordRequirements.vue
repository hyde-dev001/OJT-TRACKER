<script setup>
import { computed } from 'vue'

const props = defineProps({
  password: { type: String, default: '' },
  idPrefix: { type: String, default: 'password' },
})

const rules = computed(() => [
  { key: 'length', label: 'At least 12 characters', met: props.password.length >= 12 },
  { key: 'uppercase', label: 'An uppercase letter', met: /[A-Z]/.test(props.password) },
  { key: 'lowercase', label: 'A lowercase letter', met: /[a-z]/.test(props.password) },
  { key: 'number', label: 'A number', met: /\d/.test(props.password) },
  { key: 'symbol', label: 'A symbol', met: /[^A-Za-z0-9]/.test(props.password) },
])
</script>

<template>
  <ul :id="idPrefix + '-password-rules'" :data-testid="idPrefix + '-password-rules'" class="mt-3 grid gap-2 text-sm sm:grid-cols-2" aria-live="polite">
    <li
      v-for="rule in rules"
      :key="rule.key"
      :data-testid="'password-rule-' + rule.key"
      :class="rule.met ? 'text-emerald-700' : 'text-slate-500'"
      class="flex items-center gap-2"
    >
      <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full border text-xs font-bold" :class="rule.met ? 'border-emerald-300 bg-emerald-50' : 'border-slate-300 bg-slate-50'" aria-hidden="true">
        {{ rule.met ? '✓' : '○' }}
      </span>
      <span>{{ rule.label }}</span>
      <span class="sr-only">{{ rule.met ? 'Met' : 'Not met' }}</span>
    </li>
  </ul>
</template>
