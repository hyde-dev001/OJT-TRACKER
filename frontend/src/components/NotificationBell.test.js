import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import NotificationBell from './NotificationBell.vue'
import { listNotifications, markAllNotificationsRead, markNotificationRead } from '../services/ojt'

const routerPush = vi.hoisted(() => vi.fn())

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: routerPush }),
}))

vi.mock('../services/ojt', () => ({
  listNotifications: vi.fn(),
  markNotificationRead: vi.fn(),
  markAllNotificationsRead: vi.fn(),
}))

const firstNotification = {
  id: 'notification-1',
  event: 'assignment_created',
  title: 'New task assigned',
  message: 'Your company assigned "Weekly report".',
  destination: '/intern/tasks?task_id=8',
  read_at: null,
}

const firstPage = {
  items: [firstNotification],
  links: { next: '/api/notifications?page=2' },
  meta: { current_page: 1, last_page: 2, total: 11 },
  unread_count: 3,
}

const mountBell = async () => {
  const wrapper = mount(NotificationBell)
  await flushPromises()
  return wrapper
}

describe('NotificationBell', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    listNotifications.mockResolvedValue(firstPage)
    markNotificationRead.mockResolvedValue({ ...firstNotification, read_at: '2026-10-13T01:00:00Z' })
    markAllNotificationsRead.mockResolvedValue(undefined)
  })

  it('loads the unread count and shows the latest paginated notifications', async () => {
    const wrapper = await mountBell()

    expect(wrapper.get('[data-testid="notification-toggle"]').attributes('aria-label')).toBe('Notifications, 3 unread')
    await wrapper.get('[data-testid="notification-toggle"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-testid="notification-list"]').text()).toContain('New task assigned')
    expect(wrapper.get('[data-testid="notification-list"]').text()).toContain('Weekly report')
    expect(wrapper.get('[data-testid="notification-next-page"]').exists()).toBe(true)

    listNotifications.mockResolvedValueOnce({
      ...firstPage,
      items: [{ ...firstNotification, id: 'notification-2', title: 'Requirement due today' }],
      meta: { current_page: 2, last_page: 2, total: 11 },
    })
    await wrapper.get('[data-testid="notification-next-page"]').trigger('click')
    await flushPromises()

    expect(listNotifications).toHaveBeenLastCalledWith({ page: 2 })
    expect(wrapper.get('[data-testid="notification-list"]').text()).toContain('Requirement due today')
    wrapper.unmount()
  })

  it('shows loading, empty, and recoverable load-error states', async () => {
    let resolvePending
    listNotifications.mockReturnValueOnce(new Promise((resolve) => { resolvePending = resolve }))
    const loadingWrapper = mount(NotificationBell)
    await loadingWrapper.get('[data-testid="notification-toggle"]').trigger('click')
    expect(loadingWrapper.text()).toContain('Loading notifications')
    resolvePending({ ...firstPage, items: [], unread_count: 0, meta: { current_page: 1, last_page: 1, total: 0 } })
    await flushPromises()
    expect(loadingWrapper.text()).toContain('No notifications yet')
    loadingWrapper.unmount()

    listNotifications.mockRejectedValueOnce(new Error('Unavailable'))
    listNotifications.mockRejectedValueOnce(new Error('Still unavailable'))
    listNotifications.mockResolvedValueOnce(firstPage)
    const retryWrapper = mount(NotificationBell)
    await flushPromises()
    await retryWrapper.get('[data-testid="notification-toggle"]').trigger('click')
    await flushPromises()
    expect(retryWrapper.get('[role="alert"]').text()).toContain('The API is unavailable')
    expect(retryWrapper.text()).not.toContain('No notifications yet')

    await retryWrapper.get('[data-testid="notification-retry"]').trigger('click')
    await flushPromises()
    expect(retryWrapper.get('[data-testid="notification-list"]').text()).toContain('Weekly report')
    retryWrapper.unmount()
  })

  it('marks an item read after success and follows its destination', async () => {
    const wrapper = await mountBell()
    await wrapper.get('[data-testid="notification-toggle"]').trigger('click')
    await flushPromises()
    await wrapper.get('[data-testid="notification-item-notification-1"]').trigger('click')
    await flushPromises()

    expect(markNotificationRead).toHaveBeenCalledWith('notification-1')
    expect(routerPush).toHaveBeenCalledWith('/intern/tasks?task_id=8')
    expect(wrapper.get('[data-testid="notification-toggle"]').attributes('aria-label')).toBe('Notifications, 2 unread')
    wrapper.unmount()
  })

  it('preserves unread state and still navigates when marking one read fails', async () => {
    markNotificationRead.mockRejectedValueOnce(new Error('Unavailable'))
    const wrapper = await mountBell()
    await wrapper.get('[data-testid="notification-toggle"]').trigger('click')
    await flushPromises()
    await wrapper.get('[data-testid="notification-item-notification-1"]').trigger('click')
    await flushPromises()

    expect(routerPush).toHaveBeenCalledWith('/intern/tasks?task_id=8')
    expect(wrapper.get('[role="alert"]').text()).toContain('The API is unavailable')
    expect(wrapper.get('[data-testid="notification-toggle"]').attributes('aria-label')).toBe('Notifications, 3 unread')
    expect(wrapper.get('[data-testid="notification-item-notification-1"]').attributes('data-read')).toBe('false')
    wrapper.unmount()
  })

  it('marks all notifications read only after the API succeeds', async () => {
    const wrapper = await mountBell()
    await wrapper.get('[data-testid="notification-toggle"]').trigger('click')
    await flushPromises()
    await wrapper.get('[data-testid="notification-read-all"]').trigger('click')
    await flushPromises()

    expect(markAllNotificationsRead).toHaveBeenCalledOnce()
    expect(wrapper.get('[data-testid="notification-toggle"]').attributes('aria-label')).toBe('Notifications, 0 unread')
    expect(wrapper.get('[data-testid="notification-item-notification-1"]').attributes('data-read')).toBe('true')
    wrapper.unmount()
  })

  it('preserves read state if mark-all fails', async () => {
    markAllNotificationsRead.mockRejectedValueOnce(new Error('Unavailable'))
    const wrapper = await mountBell()
    await wrapper.get('[data-testid="notification-toggle"]').trigger('click')
    await flushPromises()
    await wrapper.get('[data-testid="notification-read-all"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('The API is unavailable')
    expect(wrapper.get('[data-testid="notification-toggle"]').attributes('aria-label')).toBe('Notifications, 3 unread')
    expect(wrapper.get('[data-testid="notification-item-notification-1"]').attributes('data-read')).toBe('false')
    wrapper.unmount()
  })
})
