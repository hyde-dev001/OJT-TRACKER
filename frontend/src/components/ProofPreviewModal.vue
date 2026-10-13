<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import AppModal from './AppModal.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: 'Proof preview' },
  file: { type: Object, default: null },
})

const emit = defineEmits(['close'])
const objectUrl = ref('')
const isPdf = computed(() => props.file?.type === 'application/pdf')

watch(() => [props.open, props.file], ([open, file]) => {
  if (objectUrl.value) URL.revokeObjectURL(objectUrl.value)
  objectUrl.value = open && file ? URL.createObjectURL(file) : ''
}, { immediate: true })

onBeforeUnmount(() => {
  if (objectUrl.value) URL.revokeObjectURL(objectUrl.value)
})
</script>

<template>
  <AppModal :open="props.open" :title="props.title" description="Private proof file" @close="emit('close')">
    <iframe v-if="isPdf" :src="objectUrl" :title="`${props.title} PDF`" class="h-[65vh] min-h-80 w-full rounded-lg border border-slate-200" />
    <img v-else-if="objectUrl" :src="objectUrl" :alt="props.title" class="mx-auto max-h-[65vh] max-w-full rounded-lg object-contain" />
    <p v-else class="text-sm text-slate-600">This proof file cannot be previewed in the browser. Download it to view it.</p>
    <template #footer>
      <button type="button" class="app-button app-button--secondary" data-testid="close-proof-preview" @click="emit('close')">Close</button>
    </template>
  </AppModal>
</template>
