<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

const props = defineProps({
  user: { type: Object, default: null },
})

const emit = defineEmits(['sign-out'])
const open = ref(false)
const container = ref(null)
const trigger = ref(null)
const initials = computed(() => {
  const words = (props.user?.name ?? '').trim().split(/\s+/).filter(Boolean)
  return words.slice(0, 2).map((word) => word[0]).join('').toUpperCase() || 'U'
})

const focusFirstItem = () => {
  nextTick(() => container.value?.querySelector('[role="menuitem"]')?.focus())
}

const close = (restoreFocus = false) => {
  open.value = false
  if (restoreFocus) nextTick(() => trigger.value?.focus())
}

const toggle = () => {
  open.value = !open.value
  if (open.value) focusFirstItem()
}

const handleOutsidePointer = (event) => {
  if (!container.value?.contains(event.target)) close()
}

const handleKeydown = (event) => {
  if (event.key === 'Escape' && open.value) {
    event.preventDefault()
    close(true)
  }
}

const chooseProfile = () => close()

const chooseSignOut = () => {
  close()
  emit('sign-out')
}

onMounted(() => {
  document.addEventListener('pointerdown', handleOutsidePointer)
  document.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', handleOutsidePointer)
  document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <div ref="container" class="account-menu-wrapper">
    <button
      ref="trigger"
      type="button"
      class="account-trigger"
      data-testid="account-trigger"
      aria-haspopup="menu"
      :aria-expanded="open"
      aria-label="Open account menu"
      aria-controls="student-account-menu"
      @click="toggle"
    >
      <span data-testid="account-avatar" class="account-avatar" aria-hidden="true">{{ initials }}</span>
    </button>

    <Transition name="account-menu">
      <div v-if="open" id="student-account-menu" data-testid="account-menu" class="account-menu" role="menu" aria-label="Student account menu">
        <div class="account-menu__summary">
          <span class="account-avatar account-avatar--menu" aria-hidden="true">{{ initials }}</span>
          <div class="min-w-0">
            <p class="truncate font-semibold text-slate-950">{{ props.user?.name || 'Student' }}</p>
            <p class="truncate text-sm text-slate-600">{{ props.user?.email || 'Student account' }}</p>
          </div>
        </div>
        <div class="account-menu__items">
          <RouterLink to="/student/profile" role="menuitem" data-testid="account-profile" class="account-menu__item" @click="chooseProfile">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21a8 8 0 0 0-16 0" /><circle cx="12" cy="7" r="4" /></svg>
            <span>Profile &amp; Password</span>
          </RouterLink>
          <button type="button" role="menuitem" data-testid="account-sign-out" class="account-menu__item account-menu__item--danger" @click="chooseSignOut">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 17l5-5-5-5" /><path d="M15 12H3" /><path d="M21 19V5a2 2 0 0 0-2-2h-6" /></svg>
            <span>Sign Out</span>
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>
