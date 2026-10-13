import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import App from './App.vue'
import { useAuthStore } from './stores/auth'

const routerPush = vi.hoisted(() => vi.fn())
const route = vi.hoisted(() => ({ name: 'student-work-hours' }))

vi.mock('vue-router', () => ({
  RouterLink: { props: ['to'], template: '<a :href="to"><slot /></a>' },
  RouterView: { template: '<div />' },
  useRoute: () => route,
  useRouter: () => ({ push: routerPush }),
}))

describe('App shell', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    useAuthStore().bootstrapped = true
    route.name = 'student-work-hours'
    vi.resetAllMocks()
  })

  it('hides application navigation on the registration screen', () => {
    route.name = 'register'

    const wrapper = mount(App)

    expect(wrapper.find('header').exists()).toBe(false)
    wrapper.unmount()
  })

  it('hides application navigation on company registration', () => {
    route.name = 'company-register'

    const wrapper = mount(App)

    expect(wrapper.find('header').exists()).toBe(false)
    wrapper.unmount()
  })

  it('does not expose public navigation while authentication is restoring', () => {
    const auth = useAuthStore()
    auth.bootstrapped = false

    const wrapper = mount(App)

    expect(wrapper.find('header').exists()).toBe(false)
    wrapper.unmount()
  })

  it('uses a compact account menu and confirms logout before calling the store', async () => {
    const auth = useAuthStore()
    auth.user = { id: 1, name: 'John Daniel Paragas', email: 'student@example.com' }
    vi.spyOn(auth, 'logout').mockResolvedValue(true)

    const wrapper = mount(App)

    expect(wrapper.text()).toContain('Overview')
    expect(wrapper.text()).toContain('Work Hours')
    expect(wrapper.text()).toContain('Tasks')
    expect(wrapper.text()).toContain('Requirements')
    expect(wrapper.text()).not.toContain('Coordinator')
    expect(wrapper.text()).not.toContain('Work Log Review')
    expect(wrapper.get('[data-testid="app-brand"]').element.tagName).toBe('DIV')
    expect(wrapper.get('[data-testid="app-brand"]').attributes('href')).toBeUndefined()
    expect(wrapper.get('[data-testid="app-brand-logo"]').exists()).toBe(true)
    expect(wrapper.get('header').classes()).toContain('sticky')
    expect(wrapper.get('[data-testid="theme-toggle"]').attributes('aria-label')).toBe('Switch to dark mode')
    expect(wrapper.get('[data-testid="account-trigger"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="user-name"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="logout-button"]').exists()).toBe(false)

    await wrapper.get('[data-testid="account-trigger"]').trigger('click')
    expect(wrapper.get('[data-testid="account-menu"]').text()).toContain('John Daniel Paragas')
    expect(wrapper.get('[data-testid="account-menu"]').text()).toContain('student@example.com')

    await wrapper.get('[data-testid="account-sign-out"]').trigger('click')
    expect(document.body.textContent).toContain('Sign out?')
    expect(auth.logout).not.toHaveBeenCalled()

    document.body.querySelector('[data-action="cancel"]').click()
    await nextTick()
    expect(auth.logout).not.toHaveBeenCalled()

    await wrapper.get('[data-testid="account-trigger"]').trigger('click')
    await wrapper.get('[data-testid="account-sign-out"]').trigger('click')
    document.body.querySelector('[data-action="confirm"]').click()
    await nextTick()
    expect(auth.logout).toHaveBeenCalledOnce()
  })

  it('keeps public navigation compact without extra section links', () => {
    route.name = 'home'

    const wrapper = mount(App)

    expect(wrapper.get('header').classes()).toContain('sticky')
    const publicLinks = wrapper.findAll('header a').map((link) => link.text())
    expect(publicLinks).not.toContain('Progress')
    expect(publicLinks).not.toContain('Features')
    wrapper.unmount()
  })

  it('opens an accessible mobile menu for public pages', async () => {
    route.name = 'home'

    const wrapper = mount(App)

    expect(wrapper.find('[data-testid="mobile-navigation-drawer"]').exists()).toBe(false)

    await wrapper.get('[data-testid="mobile-menu-trigger"]').trigger('click')

    expect(wrapper.get('[data-testid="mobile-navigation-drawer"]').text()).toContain('Home')
    expect(wrapper.get('[data-testid="mobile-navigation-drawer"]').text()).toContain('About')
    expect(wrapper.get('[data-testid="mobile-navigation-drawer"]').text()).toContain('Create student account')
    expect(wrapper.get('[data-testid="mobile-navigation-drawer"]').text()).toContain('Register company account')

    await wrapper.get('[data-testid="mobile-menu-close"]').trigger('click')
    expect(wrapper.find('[data-testid="mobile-navigation-drawer"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('provides authenticated mobile navigation and a bottom workspace nav', async () => {
    const auth = useAuthStore()
    auth.user = { id: 1, name: 'Student', role: 'student' }
    route.name = 'student-overview'

    const wrapper = mount(App)

    expect(wrapper.get('[data-testid="mobile-bottom-nav"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="mobile-bottom-nav-overview"]').text()).toContain('Overview')
    expect(wrapper.get('[data-testid="mobile-bottom-nav-work-hours"]').text()).toContain('Work Hours')
    expect(wrapper.get('[data-testid="mobile-bottom-nav-tasks"]').text()).toContain('Tasks')
    expect(wrapper.get('[data-testid="mobile-bottom-nav-requirements"]').text()).toContain('Requirements')
    expect(wrapper.find('[data-testid="mobile-menu-trigger"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="mobile-navigation-drawer"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('shows company navigation without student destinations', () => {
    const auth = useAuthStore()
    auth.user = { id: 2, name: 'Company', role: 'company' }
    route.name = 'company-interns'

    const wrapper = mount(App)

    expect(wrapper.get('[data-testid="company-workspace-link"]').attributes('href')).toBe('/company/interns')
    expect(wrapper.get('[data-testid="mobile-company-workspace-link"]').attributes('href')).toBe('/company/interns')
    expect(wrapper.get('[data-testid="company-attendance-link"]').attributes('href')).toBe('/company/attendance')
    expect(wrapper.get('[data-testid="mobile-company-attendance-link"]').attributes('href')).toBe('/company/attendance')
    expect(wrapper.get('[data-testid="company-tasks-link"]').attributes('href')).toBe('/company/tasks')
    expect(wrapper.get('[data-testid="mobile-company-tasks-link"]').attributes('href')).toBe('/company/tasks')
    expect(wrapper.get('[data-testid="company-requirements-link"]').attributes('href')).toBe('/company/requirements')
    expect(wrapper.get('[data-testid="mobile-company-requirements-link"]').attributes('href')).toBe('/company/requirements')
    expect(wrapper.text()).toContain('Attendance')
    expect(wrapper.text()).toContain('Tasks')
    expect(wrapper.text()).toContain('Requirements')
    expect(wrapper.text()).not.toContain('Work Hours')
    wrapper.unmount()
  })

  it('shows intern work hours and account navigation without student destinations', () => {
    const auth = useAuthStore()
    auth.user = { id: 8, name: 'Intern', role: 'intern' }
    route.name = 'intern-work-hours'

    const wrapper = mount(App)

    expect(wrapper.get('[data-testid="intern-work-hours-link"]').attributes('href')).toBe('/intern/work-hours')
    expect(wrapper.get('[data-testid="mobile-intern-work-hours-link"]').attributes('href')).toBe('/intern/work-hours')
    expect(wrapper.get('[data-testid="intern-workspace-link"]').attributes('href')).toBe('/intern/account')
    expect(wrapper.get('[data-testid="mobile-intern-workspace-link"]').attributes('href')).toBe('/intern/account')
    expect(wrapper.get('[data-testid="intern-tasks-link"]').attributes('href')).toBe('/intern/tasks')
    expect(wrapper.get('[data-testid="mobile-intern-tasks-link"]').attributes('href')).toBe('/intern/tasks')
    expect(wrapper.get('[data-testid="intern-requirements-link"]').attributes('href')).toBe('/intern/requirements')
    expect(wrapper.get('[data-testid="mobile-intern-requirements-link"]').attributes('href')).toBe('/intern/requirements')
    expect(wrapper.text()).toContain('Work Hours')
    expect(wrapper.text()).toContain('Tasks')
    expect(wrapper.text()).toContain('Requirements')
    wrapper.unmount()
  })

  it('returns to login when the session expires', async () => {
    const auth = useAuthStore()
    auth.user = { id: 1, name: 'Student' }
    const wrapper = mount(App)

    window.dispatchEvent(new Event('auth:expired'))
    await nextTick()

    expect(auth.user).toBeNull()
    expect(routerPush).toHaveBeenCalledWith('/login')
    wrapper.unmount()
  })
})
