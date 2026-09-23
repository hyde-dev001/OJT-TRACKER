import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import LoginView from './LoginView.vue'
import api, { ensureCsrfCookie } from '../services/api'

const routerPush = vi.hoisted(() => vi.fn())
const route = vi.hoisted(() => ({ query: {} }))

vi.mock('vue-router', () => ({
  createRouter: vi.fn(),
  createWebHistory: vi.fn(),
  RouterLink: { props: ['to'], template: '<a :href="to"><slot /></a>' },
  useRoute: () => route,
  useRouter: () => ({ push: routerPush }),
}))

vi.mock('../services/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
  },
  ensureCsrfCookie: vi.fn(),
  apiErrorMessage: vi.fn(() => 'Unable to sign in with those details.'),
}))

describe('LoginView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    route.query = {}
    vi.resetAllMocks()
  })

  it('links the brand and logo back to the home page', () => {
    const wrapper = mount(LoginView, { global: { plugins: [createPinia()] } })

    expect(wrapper.get('[data-testid="auth-brand"]').attributes('href')).toBe('/')
    expect(wrapper.get('[data-testid="auth-brand-logo"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="auth-brand-label"]').text()).toBe('OJT Progress Tracker')
    expect(wrapper.get('[data-testid="auth-brand-label"]').classes()).toContain('auth-brand__label')
  })

  it('logs in and always enters the student application', async () => {
    ensureCsrfCookie.mockResolvedValue({})
    api.post.mockResolvedValue({
      data: { user: { id: 1, name: 'Student', email: 'student@example.com' } },
    })

    const wrapper = mount(LoginView, { global: { plugins: [createPinia()] } })

    await wrapper.get('input[type="email"]').setValue('student@example.com')
    await wrapper.get('input[type="password"]').setValue('secret')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('label[for="email"]').text()).toContain('Email')
    expect(routerPush).toHaveBeenCalledWith('/student/overview')
  })

  it('preserves only internal student deep links after login', async () => {
    ensureCsrfCookie.mockResolvedValue({})
    api.post.mockResolvedValue({
      data: { user: { id: 1, name: 'Student', email: 'student@example.com' } },
    })
    route.query = { redirect: '/student/tasks' }

    const wrapper = mount(LoginView, { global: { plugins: [createPinia()] } })
    await wrapper.get('input[type="email"]').setValue('student@example.com')
    await wrapper.get('input[type="password"]').setValue('secret')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(routerPush).toHaveBeenCalledWith('/student/tasks')

    routerPush.mockReset()
    route.query = { redirect: 'https://evil.example' }
    const secondWrapper = mount(LoginView, { global: { plugins: [createPinia()] } })
    await secondWrapper.get('input[type="email"]').setValue('student@example.com')
    await secondWrapper.get('input[type="password"]').setValue('secret')
    await secondWrapper.get('form').trigger('submit')
    await flushPromises()

    expect(routerPush).toHaveBeenCalledWith('/student/overview')
  })

  it('shows a safe validation error without exposing the exception', async () => {
    ensureCsrfCookie.mockResolvedValue({})
    api.post.mockRejectedValue({
      response: {
        status: 422,
        data: { errors: { auth: ['Unable to sign in. Please try again.'] } },
      },
    })

    const wrapper = mount(LoginView, { global: { plugins: [createPinia()] } })

    await wrapper.get('input[type="email"]').setValue('student@example.com')
    await wrapper.get('input[type="password"]').setValue('wrong')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Unable to sign in. Please try again.')
    expect(wrapper.text()).not.toContain('These credentials do not match our records.')
    expect(wrapper.find('#email-error').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('AxiosError')
  })

  it('toggles password visibility without changing the submitted value', async () => {
    const wrapper = mount(LoginView, { global: { plugins: [createPinia()] } })

    await wrapper.get('input[type="password"]').setValue('secret')
    await wrapper.get('[data-toggle-password]').trigger('click')

    expect(wrapper.get('#password').attributes('type')).toBe('text')
    expect(wrapper.get('#password').element.value).toBe('secret')
    expect(wrapper.get('[data-toggle-password]').attributes('aria-pressed')).toBe('true')
  })
})
