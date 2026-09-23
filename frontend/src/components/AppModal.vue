<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, required: true },
  description: { type: String, default: '' },
  closeOnBackdrop: { type: Boolean, default: true },
  closeOnEscape: { type: Boolean, default: true },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['close'])
const dialog = ref(null)
const restoreTarget = ref(null)

function focusDialog() {
  nextTick(() => dialog.value?.focus())
}

function requestClose() {
  if (!props.busy) emit('close')
}

function handleKeydown(event) {
  if (event.key === 'Escape') {
    if (props.closeOnEscape) requestClose()
    return
  }

  if (event.key !== 'Tab') return

  const focusable = [...(dialog.value?.querySelectorAll(
    'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
  ) ?? [])]
  if (!focusable.length) {
    event.preventDefault()
    dialog.value?.focus()
    return
  }

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

function handleOpen(open) {
  if (open) {
    restoreTarget.value = document.activeElement instanceof HTMLElement ? document.activeElement : null
    focusDialog()
    return
  }

  restoreTarget.value?.focus?.()
  restoreTarget.value = null
}

watch(() => props.open, handleOpen, { immediate: true })
onBeforeUnmount(() => restoreTarget.value?.focus?.())
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="app-modal-backdrop" data-modal-backdrop @click.self="closeOnBackdrop && requestClose()">
      <section
        ref="dialog"
        class="app-modal"
        role="dialog"
        aria-modal="true"
        tabindex="-1"
        :aria-labelledby="`modal-title-${title.replaceAll(/\W+/g, '-').toLowerCase()}`"
        :aria-describedby="description ? `modal-description-${title.replaceAll(/\W+/g, '-').toLowerCase()}` : undefined"
        @keydown="handleKeydown"
      >
        <header class="app-modal__header">
          <div>
            <h2 :id="`modal-title-${title.replaceAll(/\W+/g, '-').toLowerCase()}`" class="text-lg font-semibold text-slate-950">
              {{ title }}
            </h2>
            <p v-if="description" :id="`modal-description-${title.replaceAll(/\W+/g, '-').toLowerCase()}`" class="mt-1 text-sm text-slate-600">
              {{ description }}
            </p>
          </div>
        </header>

        <div class="app-modal__body">
          <slot />
        </div>

        <footer v-if="$slots.footer" class="app-modal__footer">
          <slot name="footer" />
        </footer>
      </section>
    </div>
  </Teleport>
</template>
