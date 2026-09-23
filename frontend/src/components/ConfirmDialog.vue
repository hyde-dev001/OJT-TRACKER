<script setup>
import AppModal from './AppModal.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, required: true },
  message: { type: String, required: true },
  confirmLabel: { type: String, default: 'Confirm' },
  cancelLabel: { type: String, default: 'Cancel' },
  busyLabel: { type: String, default: '' },
  variant: { type: String, default: 'primary' },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['cancel', 'confirm'])
</script>

<template>
  <AppModal :open="props.open" :title="props.title" :busy="props.busy" @close="emit('cancel')">
    <p class="text-sm leading-6 text-slate-600">{{ props.message }}</p>
    <template #footer>
      <button type="button" class="app-button app-button--secondary" data-action="cancel" :disabled="props.busy" @click="emit('cancel')">
        {{ props.cancelLabel }}
      </button>
      <button
        type="button"
        :class="['app-button', props.variant === 'danger' ? 'app-button--danger' : 'app-button--primary']"
        data-action="confirm"
        :disabled="props.busy"
        @click="emit('confirm')"
      >
        {{ props.busy ? (props.busyLabel || 'Working…') : props.confirmLabel }}
      </button>
    </template>
  </AppModal>
</template>
