<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  variant: { type: String, default: 'success' },
  title: { type: String, required: true },
  message: { type: String, required: true },
})

const emit = defineEmits(['close'])
const dialog = ref(null)
const restoreTarget = ref(null)

const handleKeydown = (event) => {
  if (event.key === 'Escape') {
    emit('close')
    return
  }

  if (event.key !== 'Tab') return

  const focusable = [...(dialog.value?.querySelectorAll('button:not([disabled]), [href], [tabindex]:not([tabindex="-1"])') ?? [])]
  if (!focusable.length) return

  const first = focusable[0]
  const last = focusable.at(-1)
  const active = document.activeElement

  if (event.shiftKey && (active === dialog.value || active === first)) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && (active === dialog.value || active === last)) {
    event.preventDefault()
    first.focus()
  }
}

watch(() => props.open, (open) => {
  if (open) {
    restoreTarget.value = document.activeElement instanceof HTMLElement ? document.activeElement : null
    nextTick(() => dialog.value?.focus())
    return
  }

  restoreTarget.value?.focus?.()
  restoreTarget.value = null
}, { immediate: true })

onBeforeUnmount(() => restoreTarget.value?.focus?.())
</script>

<template>
  <Teleport to="body">
    <div v-if="props.open" class="action-alert-backdrop fixed inset-0 z-[70] grid place-items-center bg-slate-950/25 px-4" @click.self="emit('close')">
      <section
        ref="dialog"
        data-testid="action-alert"
        class="action-alert w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-2xl"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="action-alert-title"
        aria-describedby="action-alert-message"
        tabindex="-1"
        @keydown="handleKeydown"
      >
        <div
          :class="[
            'mx-auto grid h-12 w-12 place-items-center rounded-full text-2xl font-bold',
            props.variant === 'error' ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700',
          ]"
          aria-hidden="true"
        >
          <span v-if="props.variant === 'error'">!</span>
          <span v-else>&#10003;</span>
        </div>
        <h2 id="action-alert-title" class="mt-4 text-xl font-semibold text-slate-950">{{ props.title }}</h2>
        <p id="action-alert-message" class="mt-2 text-sm leading-6 text-slate-600">{{ props.message }}</p>
        <button type="button" class="app-button app-button--primary mt-6 min-w-24" data-action="alert-close" data-testid="alert-close" @click="emit('close')">OK</button>
      </section>
    </div>
  </Teleport>
</template>
