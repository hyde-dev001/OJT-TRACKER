<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { apiErrorMessage } from '../services/api'
import { listNotifications, markAllNotificationsRead, markNotificationRead } from '../services/ojt'

const router = useRouter()
const container = ref(null)
const trigger = ref(null)
const open = ref(false)
const loading = ref(false)
const notifications = ref([])
const unreadCount = ref(null)
const currentPage = ref(1)
const meta = ref(null)
const loadError = ref('')
const actionError = ref('')
const unreadLabel = computed(() => unreadCount.value === null
  ? 'Notifications, unread count unavailable'
  : `Notifications, ${unreadCount.value} unread`)
const unreadBadge = computed(() => unreadCount.value === null
  ? '?'
  : unreadCount.value > 99 ? '99+' : unreadCount.value)
const hasUnread = computed(() => unreadCount.value === null
  ? notifications.value.some((notification) => !notification.read_at)
  : unreadCount.value > 0)

const loadNotifications = async (page = 1) => {
  loading.value = true
  actionError.value = ''

  try {
    const result = await listNotifications({ page })
    notifications.value = result.items
    meta.value = result.meta
    currentPage.value = result.meta?.current_page ?? page
    unreadCount.value = result.unread_count
    loadError.value = ''
  } catch (error) {
    loadError.value = apiErrorMessage(error, 'Unable to load notifications. Please try again.')
  } finally {
    loading.value = false
  }
}

const toggle = async () => {
  if (open.value) {
    open.value = false
    return
  }

  open.value = true
  if (loading.value) return
  await loadNotifications(1)
}

const markAllRead = async () => {
  try {
    await markAllNotificationsRead()
    const readAt = new Date().toISOString()
    notifications.value = notifications.value.map((notification) => ({
      ...notification,
      read_at: notification.read_at ?? readAt,
    }))
    unreadCount.value = 0
    actionError.value = ''
  } catch (error) {
    actionError.value = apiErrorMessage(error, 'Unable to mark notifications as read. Please try again.')
  }
}

const followNotification = async (notification) => {
  if (!notification.destination) return

  if (!notification.read_at) {
    let updated
    try {
      updated = await markNotificationRead(notification.id)
    } catch (error) {
      actionError.value = apiErrorMessage(error, 'Unable to mark this notification as read. Please try again.')
      await router.push(notification.destination)
      return
    }

    notifications.value = notifications.value.map((item) => item.id === notification.id
      ? { ...item, read_at: updated?.read_at ?? new Date().toISOString() }
      : item)
    if (unreadCount.value !== null) unreadCount.value = Math.max(0, unreadCount.value - 1)
    actionError.value = ''
  }

  open.value = false
  await router.push(notification.destination)
}

const closeOnOutsidePointer = (event) => {
  if (!container.value?.contains(event.target)) open.value = false
}

const closeOnEscape = (event) => {
  if (event.key === 'Escape' && open.value) {
    event.preventDefault()
    open.value = false
    trigger.value?.focus()
  }
}

onMounted(() => {
  document.addEventListener('pointerdown', closeOnOutsidePointer)
  document.addEventListener('keydown', closeOnEscape)
  loadNotifications()
})

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', closeOnOutsidePointer)
  document.removeEventListener('keydown', closeOnEscape)
})
</script>

<template>
  <div ref="container" class="relative shrink-0">
    <button
      ref="trigger"
      type="button"
      class="relative grid h-10 w-10 place-items-center rounded-full border border-transparent text-slate-700 hover:border-slate-200 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-700"
      data-testid="notification-toggle"
      aria-haspopup="dialog"
      aria-controls="notification-panel"
      :aria-expanded="open"
      :aria-label="unreadLabel"
      title="Notifications"
      @click="toggle"
    >
      <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></svg>
      <span class="absolute -right-1 -top-1 min-w-5 rounded-full bg-slate-950 px-1 text-center text-[10px] leading-5 text-white" data-testid="notification-unread-count" aria-hidden="true">{{ unreadBadge }}</span>
    </button>

    <Transition name="account-menu">
      <section
        v-if="open"
        id="notification-panel"
        class="absolute right-0 z-50 mt-2 flex max-h-[min(70vh,38rem)] w-[min(24rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-xl border border-slate-200 bg-white text-left text-slate-900 shadow-xl"
        data-testid="notification-panel"
        role="dialog"
        aria-label="Notifications"
      >
        <header class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
          <h2 class="font-semibold">Notifications</h2>
          <button v-if="hasUnread" type="button" class="text-sm font-semibold text-slate-700 underline underline-offset-2 hover:text-slate-950" data-testid="notification-read-all" @click="markAllRead">Mark all as read</button>
        </header>

        <p v-if="actionError" class="mx-4 mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">{{ actionError }}</p>
        <div class="overflow-y-auto p-2">
          <p v-if="loading" class="px-3 py-4 text-sm text-slate-600" data-testid="notification-loading" role="status">Loading notifications…</p>
          <p v-if="loadError" class="mx-2 my-2 rounded-lg border border-red-200 bg-red-50 px-3 py-3 text-sm text-red-800" data-testid="notification-load-error" role="alert">
            {{ loadError }}
            <button type="button" class="ml-1 font-semibold underline underline-offset-2" data-testid="notification-retry" @click="loadNotifications(currentPage)">Retry</button>
          </p>
          <p v-if="!loading && !loadError && notifications.length === 0" class="px-3 py-6 text-center text-sm text-slate-600">No notifications yet.</p>
          <ul v-if="notifications.length" class="space-y-1" data-testid="notification-list">
            <li v-for="notification in notifications" :key="notification.id">
              <button
                type="button"
                class="flex w-full items-start gap-3 rounded-lg px-3 py-3 text-left hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-slate-700"
                :class="notification.read_at ? 'text-slate-600' : 'bg-slate-50 text-slate-950'"
                :data-testid="`notification-item-${notification.id}`"
                :data-read="Boolean(notification.read_at)"
                @click="followNotification(notification)"
              >
                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="notification.read_at ? 'bg-transparent' : 'bg-blue-600'" :aria-label="notification.read_at ? 'Read' : 'Unread'" />
                <span class="min-w-0 flex-1">
                  <span class="block font-semibold">{{ notification.title }}</span>
                  <span class="mt-1 block text-sm font-normal text-slate-600">{{ notification.message }}</span>
                </span>
              </button>
            </li>
          </ul>
        </div>

        <footer v-if="meta && meta.last_page > 1" class="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm">
          <span class="text-slate-600">Page {{ currentPage }} of {{ meta.last_page }}</span>
          <div class="flex gap-2">
            <button type="button" class="rounded-md border border-slate-200 px-2 py-1 disabled:cursor-not-allowed disabled:opacity-40" data-testid="notification-previous-page" :disabled="loading || currentPage <= 1" @click="loadNotifications(currentPage - 1)">Previous</button>
            <button type="button" class="rounded-md border border-slate-200 px-2 py-1 disabled:cursor-not-allowed disabled:opacity-40" data-testid="notification-next-page" :disabled="loading || currentPage >= meta.last_page" @click="loadNotifications(currentPage + 1)">Next</button>
          </div>
        </footer>
      </section>
    </Transition>
  </div>
</template>
