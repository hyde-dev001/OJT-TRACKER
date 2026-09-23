<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'

defineProps({
  open: { type: Boolean, default: false },
  authenticated: { type: Boolean, default: false },
})

const emit = defineEmits(['close'])
const drawer = ref(null)

const focusableElements = () => Array.from(drawer.value?.querySelectorAll('a[href], button:not([disabled])') ?? [])

const focusFirstItem = () => nextTick(() => focusableElements()[0]?.focus())

const restoreFocus = () => nextTick(() => document.querySelector('[data-testid="mobile-menu-trigger"]')?.focus())

const close = (shouldRestoreFocus = true) => {
  emit('close')
  if (shouldRestoreFocus) restoreFocus()
}

const handleKeydown = (event) => {
  if (!drawer.value) return

  if (event.key === 'Escape') {
    event.preventDefault()
    close()
    return
  }

  if (event.key !== 'Tab') return

  const items = focusableElements()
  if (!items.length) return

  const first = items[0]
  const last = items[items.length - 1]
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}

const handleBackdropClick = (event) => {
  if (event.target === event.currentTarget) close()
}

watch(() => drawer.value, (element) => {
  if (element) focusFirstItem()
})

onMounted(() => document.addEventListener('keydown', handleKeydown))
onBeforeUnmount(() => document.removeEventListener('keydown', handleKeydown))
</script>

<template>
  <div v-if="open" class="mobile-navigation-backdrop" data-testid="mobile-navigation-backdrop" @click="handleBackdropClick">
    <aside ref="drawer" class="mobile-navigation-drawer" data-testid="mobile-navigation-drawer" role="dialog" aria-modal="true" aria-label="Mobile navigation">
      <div class="mobile-navigation-drawer__header">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-500">Menu</p>
        <button type="button" class="mobile-menu-close" data-testid="mobile-menu-close" aria-label="Close menu" @click="close()">×</button>
      </div>

      <nav class="mobile-navigation-drawer__links" aria-label="Mobile navigation links">
        <template v-if="authenticated">
          <RouterLink to="/student/overview" class="mobile-navigation-link" @click="close(false)">Overview</RouterLink>
          <RouterLink to="/student/work-hours" class="mobile-navigation-link" @click="close(false)">Work Hours</RouterLink>
          <RouterLink to="/student/tasks" class="mobile-navigation-link" @click="close(false)">Tasks</RouterLink>
          <RouterLink to="/student/requirements" class="mobile-navigation-link" @click="close(false)">Requirements</RouterLink>
        </template>
        <template v-else>
          <RouterLink to="/" class="mobile-navigation-link" @click="close(false)">Home</RouterLink>
          <RouterLink to="/about" class="mobile-navigation-link" @click="close(false)">About</RouterLink>
          <div class="mobile-navigation-divider" aria-hidden="true"></div>
          <RouterLink to="/login" class="mobile-navigation-link" @click="close(false)">Sign in</RouterLink>
          <RouterLink to="/register" class="mobile-navigation-link mobile-navigation-link--primary" @click="close(false)">Create student account</RouterLink>
        </template>
      </nav>
    </aside>
  </div>
</template>
