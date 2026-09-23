<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import ConfirmDialog from './components/ConfirmDialog.vue'
import MobileNavigationDrawer from './components/MobileNavigationDrawer.vue'
import StudentAccountMenu from './components/StudentAccountMenu.vue'
import { useAuthStore } from './stores/auth'
import { useAppStore } from './stores/app'
import { useTheme } from './composables/useTheme'

const appStore = useAppStore()
const authStore = useAuthStore()
const { theme, toggleTheme } = useTheme()
const route = useRoute()
const router = useRouter()
const logoutDialogOpen = ref(false)
const logoutBusy = ref(false)
const mobileMenuOpen = ref(false)
const isAuthScreen = computed(() => ['login', 'register'].includes(route.name))

watch(() => route.name, () => {
  mobileMenuOpen.value = false
})

const handleSessionExpired = () => {
  if (!authStore.user) return

  authStore.clearSession()
  logoutDialogOpen.value = false
  router.push('/login')
}

onMounted(() => window.addEventListener('auth:expired', handleSessionExpired))
onBeforeUnmount(() => window.removeEventListener('auth:expired', handleSessionExpired))

const openLogoutDialog = () => {
  mobileMenuOpen.value = false
  logoutDialogOpen.value = true
}

const cancelLogout = () => {
  if (!logoutBusy.value) logoutDialogOpen.value = false
}

const logout = async () => {
  logoutBusy.value = true
  try {
    if (!await authStore.logout()) return

    logoutDialogOpen.value = false
    await router.push('/login')
  } finally {
    logoutBusy.value = false
  }
}

const themeLabel = computed(() => theme.value === 'dark' ? 'Switch to light mode' : 'Switch to dark mode')
</script>

<template>
  <div :class="['app-shell min-h-screen bg-slate-50 text-slate-900', { 'app-shell--authenticated': authStore.isAuthenticated }]">
    <header v-if="!isAuthScreen && (authStore.bootstrapped || authStore.isAuthenticated)" class="app-header sticky top-0 z-40 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur">
      <nav class="mx-auto flex min-h-16 max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8" aria-label="Main navigation">
        <div data-testid="app-brand" class="flex items-center gap-2 font-semibold tracking-tight text-slate-950">
          <span data-testid="app-brand-logo" class="grid h-8 w-8 place-items-center rounded-lg bg-slate-950 text-sm font-bold text-white" aria-hidden="true">O</span>
          <span class="app-brand-label">{{ appStore.applicationName }}</span>
        </div>
        <div v-if="authStore.isAuthenticated" class="app-header-actions app-header-actions--authenticated flex min-w-0 flex-1 flex-wrap items-center justify-end gap-2 text-sm font-semibold">
          <div class="desktop-workspace-nav flex min-w-0 flex-1 flex-wrap justify-end gap-1" aria-label="Workspace navigation">
            <RouterLink to="/student/overview" active-class="app-nav-link--active" class="app-nav-link">Overview</RouterLink>
            <RouterLink to="/student/work-hours" active-class="app-nav-link--active" class="app-nav-link">Work Hours</RouterLink>
            <RouterLink to="/student/tasks" active-class="app-nav-link--active" class="app-nav-link">Tasks</RouterLink>
            <RouterLink to="/student/requirements" active-class="app-nav-link--active" class="app-nav-link">Requirements</RouterLink>
          </div>
          <button type="button" class="theme-toggle" data-testid="theme-toggle" :aria-label="themeLabel" :title="themeLabel" @click="toggleTheme">
            <svg v-if="theme === 'dark'" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" /></svg>
            <svg v-else viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.5 14.5A8.5 8.5 0 0 1 9.5 3.5 8.5 8.5 0 1 0 20.5 14.5Z" /></svg>
          </button>
          <StudentAccountMenu :user="authStore.user" @sign-out="openLogoutDialog" />
        </div>
        <div v-else class="app-header-actions app-header-actions--public flex items-center gap-2 text-sm font-semibold">
          <div class="desktop-public-nav flex items-center gap-1">
            <RouterLink to="/" active-class="app-nav-link--active" class="app-nav-link public-nav-link">Home</RouterLink>
            <RouterLink to="/about" active-class="app-nav-link--active" class="app-nav-link public-nav-link">About</RouterLink>
          </div>
          <button type="button" class="theme-toggle" data-testid="theme-toggle" :aria-label="themeLabel" :title="themeLabel" @click="toggleTheme">
            <svg v-if="theme === 'dark'" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" /></svg>
            <svg v-else viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.5 14.5A8.5 8.5 0 0 1 9.5 3.5 8.5 8.5 0 1 0 20.5 14.5Z" /></svg>
          </button>
          <RouterLink to="/login" class="app-button app-button--primary desktop-public-sign-in">Sign in</RouterLink>
          <button type="button" class="mobile-menu-trigger" data-testid="mobile-menu-trigger" aria-label="Open menu" aria-controls="mobile-navigation-drawer" :aria-expanded="mobileMenuOpen" @click="mobileMenuOpen = true">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
          </button>
        </div>
      </nav>
    </header>

    <RouterView v-slot="{ Component, route: viewRoute }">
      <component v-if="viewRoute.meta.requiresAuth" :is="Component" :key="viewRoute.name" />
      <Transition v-else name="public-route" mode="out-in">
        <component :is="Component" :key="viewRoute.name" />
      </Transition>
    </RouterView>

    <nav v-if="authStore.isAuthenticated && !isAuthScreen" class="mobile-bottom-nav" data-testid="mobile-bottom-nav" aria-label="Student workspace navigation">
      <RouterLink to="/student/overview" active-class="app-nav-link--active" class="mobile-bottom-nav__link" data-testid="mobile-bottom-nav-overview">
        <svg class="mobile-bottom-nav__icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 10.5 12 3l9 7.5" /><path d="M5.5 9.5V21h13V9.5M9.5 21v-6h5v6" /></svg>
        <span>Overview</span>
      </RouterLink>
      <RouterLink to="/student/work-hours" active-class="app-nav-link--active" class="mobile-bottom-nav__link" data-testid="mobile-bottom-nav-work-hours">
        <svg class="mobile-bottom-nav__icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="5" width="16" height="15" rx="2" /><path d="M8 3v4M16 3v4M4 10h16" /></svg>
        <span>Work Hours</span>
      </RouterLink>
      <RouterLink to="/student/tasks" active-class="app-nav-link--active" class="mobile-bottom-nav__link" data-testid="mobile-bottom-nav-tasks">
        <svg class="mobile-bottom-nav__icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 6h11M9 12h11M9 18h11" /><path d="m4 6 1.2 1.2L7.5 5M4 12l1.2 1.2L7.5 11M4 18l1.2 1.2L7.5 17" /></svg>
        <span>Tasks</span>
      </RouterLink>
      <RouterLink to="/student/requirements" active-class="app-nav-link--active" class="mobile-bottom-nav__link" data-testid="mobile-bottom-nav-requirements">
        <svg class="mobile-bottom-nav__icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 3h8l4 4v14H7z" /><path d="M15 3v5h4M10 13h6M10 17h6" /></svg>
        <span>Requirements</span>
      </RouterLink>
    </nav>

    <MobileNavigationDrawer
      :open="mobileMenuOpen"
      :authenticated="authStore.isAuthenticated"
      @close="mobileMenuOpen = false"
    />

    <ConfirmDialog
      :open="logoutDialogOpen"
      title="Sign out?"
      :message="authStore.error || 'Your current session will be closed on this device.'"
      confirm-label="Sign out"
      busy-label="Signing out…"
      :busy="logoutBusy"
      @cancel="cancelLogout"
      @confirm="logout"
    />
  </div>
</template>
