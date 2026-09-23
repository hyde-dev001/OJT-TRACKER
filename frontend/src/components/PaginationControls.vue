<script setup>
import { computed } from 'vue'

const props = defineProps({
  meta: { type: Object, default: null },
})

const emit = defineEmits(['change'])

const currentPage = computed(() => Number(props.meta?.current_page) || 1)
const lastPage = computed(() => Number(props.meta?.last_page) || 1)
const hasPages = computed(() => lastPage.value > 1)
const pageNumbers = computed(() => {
  const windowSize = 5
  const start = Math.max(1, Math.min(currentPage.value - 2, lastPage.value - windowSize + 1))
  const end = Math.min(lastPage.value, start + windowSize - 1)
  return Array.from({ length: end - start + 1 }, (_, index) => start + index)
})
const summary = computed(() => {
  const total = Number(props.meta?.total) || 0
  if (!total) return 'No items'
  return `Showing ${props.meta.from ?? 1}–${props.meta.to ?? total} of ${total}`
})

function change(page) {
  if (page >= 1 && page <= lastPage.value && page !== currentPage.value) emit('change', page)
}
</script>

<template>
  <nav v-if="meta" class="flex flex-col gap-3 border-t border-slate-200 pt-4 text-sm sm:flex-row sm:items-center sm:justify-between" aria-label="Pagination">
    <p class="text-slate-600">{{ summary }}</p>
    <div v-if="hasPages" class="flex items-center gap-1">
      <button type="button" class="pagination-button" :disabled="currentPage === 1" aria-label="Previous page" @click="change(currentPage - 1)">
        Previous
      </button>
      <button
        v-for="page in pageNumbers"
        :key="page"
        type="button"
        :class="['pagination-button pagination-button--page', page === currentPage && 'pagination-button--active']"
        :data-page="page"
        :aria-current="page === currentPage ? 'page' : undefined"
        @click="change(page)"
      >
        {{ page }}
      </button>
      <button type="button" class="pagination-button" :disabled="currentPage === lastPage" aria-label="Next page" @click="change(currentPage + 1)">
        Next
      </button>
    </div>
  </nav>
</template>
