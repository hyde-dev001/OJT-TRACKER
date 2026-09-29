import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia } from 'pinia'
import RegisterView from './RegisterView.vue'
import api, { ensureCsrfCookie } from '../services/api'

const routerPush = vi.hoisted(() => vi.fn())

vi.mock('vue-router', () => ({
  RouterLink: { props: ['to'], template: '<a :href="to"><slot /></a>' },
  useRouter: () => ({ push: routerPush }),
}))

vi.mock('../services/api', () => ({
  default: { get: vi.fn(), post: vi.fn() },
  ensureCsrfCookie: vi.fn(),
  apiErrorMessage: vi.fn(() => 'Please correct the highlighted fields.'),
}))

describe('RegisterView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    api.get.mockResolvedValue({ data: { available: true } })
  })

  it('links the brand and logo back to the home page', () => {
    const wrapper = mount(RegisterView, { global: { plugins: [createPinia()] } })

    expect(wrapper.get('[data-testid="auth-brand"]').attributes('href')).toBe('/')
    expect(wrapper.get('[data-testid="auth-brand-logo"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="auth-brand-label"]').text()).toBe('OJT Progress Tracker')
    expect(wrapper.get('[data-testid="auth-brand-label"]').classes()).toContain('auth-brand__label')
  })

  it('starts with account setup and the required password policy', () => {
    const wrapper = mount(RegisterView, { global: { plugins: [createPinia()] } })
    const password = wrapper.get('#register-password')

    expect(wrapper.get('[data-testid="registration-step-1"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="registration-step-2"]').exists()).toBe(false)
    expect(wrapper.get('#register-first-name').exists()).toBe(true)
    expect(wrapper.get('#register-last-name').exists()).toBe(true)
    expect(wrapper.get('#register-suffix').exists()).toBe(true)
    expect(wrapper.get('[data-testid="register-name-fields"]').find('#register-suffix').exists()).toBe(true)
    expect(password.attributes('minlength')).toBe('12')
    expect(password.attributes('pattern')).toBe('(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[^A-Za-z0-9]).{12,}')
    expect(wrapper.text()).toContain('12 characters')
    expect(wrapper.text()).toContain('number')
    expect(wrapper.text()).toContain('uppercase')
    expect(wrapper.text()).toContain('lowercase')
    expect(wrapper.text()).toContain('symbol')
  })

  it('shows which password requirements are met while typing', async () => {
    const wrapper = mount(RegisterView, { global: { plugins: [createPinia()] } })
    const password = wrapper.get('#register-password')

    expect(wrapper.get('[data-testid="password-rule-uppercase"]').text()).toContain('Not met')
    expect(wrapper.get('[data-testid="password-rule-number"]').text()).toContain('Not met')

    await password.setValue('Abcdefghijk!')

    expect(wrapper.get('[data-testid="password-rule-uppercase"]').text()).toContain('Met')
    expect(wrapper.get('[data-testid="password-rule-number"]').text()).toContain('Not met')
    expect(wrapper.get('[data-testid="password-rule-uppercase"]').classes()).toContain('text-emerald-700')

    await password.setValue('Abcdefghij1!')

    expect(wrapper.get('[data-testid="password-rule-number"]').text()).toContain('Met')
    expect(wrapper.get('[data-testid="password-rule-number"]').classes()).toContain('text-emerald-700')
  })

  it('does not advance when account setup is invalid', async () => {
    const wrapper = mount(RegisterView, { global: { plugins: [createPinia()] } })

    await wrapper.get('[data-testid="account-setup-form"]').trigger('submit')

    expect(wrapper.get('[data-testid="registration-step-1"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="registration-step-2"]').exists()).toBe(false)
  })

  it('advances to OJT setup only after valid account details', async () => {
    const wrapper = mount(RegisterView, { global: { plugins: [createPinia()] } })

    await fillAccount(wrapper)
    await wrapper.get('[data-testid="account-setup-form"]').trigger('submit')

    expect(wrapper.find('[data-testid="registration-step-1"]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="registration-step-2"]').exists()).toBe(true)
    expect(wrapper.get('#register-required-hours').exists()).toBe(true)
    expect(wrapper.get('#register-start-date').exists()).toBe(true)
    expect(wrapper.get('#register-end-date').exists()).toBe(true)
    expect(wrapper.get('[data-testid="register-work-days"]').exists()).toBe(true)
    expect(wrapper.get('#register-expected-hours-per-day').exists()).toBe(true)
  })

  it('keeps the user on account setup when the email already exists', async () => {
    api.get.mockRejectedValueOnce({
      response: {
        status: 422,
        data: { errors: { email: ['An account already exists with this email.'] } },
      },
    })
    const wrapper = mount(RegisterView, { global: { plugins: [createPinia()] } })

    await fillAccount(wrapper)
    await wrapper.get('[data-testid="account-setup-form"]').trigger('submit')
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/register/check-email', { params: { email: 'new@example.com' } })
    expect(wrapper.get('[data-testid="registration-step-1"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="registration-step-2"]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="field-error-email"]').text()).toContain('An account already exists with this email.')
  })

  it('supports show and hide for both password fields', async () => {
    const wrapper = mount(RegisterView, { global: { plugins: [createPinia()] } })

    expect(wrapper.get('#register-password').attributes('type')).toBe('password')
    expect(wrapper.get('#register-password-confirmation').attributes('type')).toBe('password')

    await wrapper.get('[data-testid="toggle-register-password"]').trigger('click')
    await wrapper.get('[data-testid="toggle-register-password-confirmation"]').trigger('click')

    expect(wrapper.get('#register-password').attributes('type')).toBe('text')
    expect(wrapper.get('#register-password-confirmation').attributes('type')).toBe('text')

    await wrapper.get('[data-testid="toggle-register-password"]').trigger('click')
    await wrapper.get('[data-testid="toggle-register-password-confirmation"]').trigger('click')

    expect(wrapper.get('#register-password').attributes('type')).toBe('password')
    expect(wrapper.get('#register-password-confirmation').attributes('type')).toBe('password')
  })

  it('returns to account setup with values intact when Back is clicked', async () => {
    const wrapper = mount(RegisterView, { global: { plugins: [createPinia()] } })

    await fillAccount(wrapper)
    await wrapper.get('[data-testid="account-setup-form"]').trigger('submit')
    await wrapper.get('[data-testid="back-registration"]').trigger('click')

    expect(wrapper.get('#register-first-name').element.value).toBe('New')
    expect(wrapper.get('#register-last-name').element.value).toBe('Student')
    expect(wrapper.get('#register-suffix').element.value).toBe('Jr.')
    expect(wrapper.get('#register-email').element.value).toBe('new@example.com')
    expect(wrapper.get('#register-password').element.value).toBe('StrongPassword1!')
    expect(wrapper.get('#register-password-confirmation').element.value).toBe('StrongPassword1!')
  })

  it('blocks an invalid target end date before submitting', async () => {
    const wrapper = mount(RegisterView, { global: { plugins: [createPinia()] } })

    await fillAccount(wrapper)
    await wrapper.get('[data-testid="account-setup-form"]').trigger('submit')
    await chooseDate(wrapper, 'register-start-date', '2026-09-01')
    await chooseDate(wrapper, 'register-end-date', '2026-09-30')
    await chooseDate(wrapper, 'register-start-date', '2026-10-01')
    await wrapper.get('[data-testid="ojt-setup-form"]').trigger('submit')

    expect(wrapper.get('[data-testid="end-date-error"]').text()).toContain('Target end date must be after your OJT start date.')
    expect(api.post).not.toHaveBeenCalled()
  })

  it('submits account and OJT setup data together', async () => {
    ensureCsrfCookie.mockResolvedValue({})
    api.post.mockResolvedValue({
      data: { user: { id: 2, name: 'New Student', email: 'new@example.com' } },
    })
    const wrapper = mount(RegisterView, { global: { plugins: [createPinia()] } })

    await fillAccount(wrapper)
    await wrapper.get('[data-testid="account-setup-form"]').trigger('submit')
    await wrapper.get('#register-required-hours').setValue('500')
    await chooseDate(wrapper, 'register-start-date', '2026-09-01')
    await chooseDate(wrapper, 'register-end-date', '2026-09-30')
    await wrapper.get('[data-testid="ojt-setup-form"]').trigger('submit')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith('/register', {
      first_name: 'New',
      last_name: 'Student',
      suffix: 'Jr.',
      email: 'new@example.com',
      password: 'StrongPassword1!',
      password_confirmation: 'StrongPassword1!',
      required_hours: 500,
      start_date: '2026-09-01',
      end_date: '2026-09-30',
      work_days: [1, 2, 3, 4, 5],
      expected_hours_per_day: 8,
    })
    expect(routerPush).toHaveBeenCalledWith('/student/overview')
  })

  it('returns to account setup when the backend reports an account error', async () => {
    ensureCsrfCookie.mockResolvedValue({})
    api.post.mockRejectedValue({
      response: {
        status: 422,
        data: { errors: { email: ['An account already exists with this email.'] } },
      },
    })
    const wrapper = mount(RegisterView, { global: { plugins: [createPinia()] } })

    await fillAccount(wrapper)
    await wrapper.get('[data-testid="account-setup-form"]').trigger('submit')
    await chooseDate(wrapper, 'register-start-date', '2026-09-01')
    await chooseDate(wrapper, 'register-end-date', '2026-09-30')
    await wrapper.get('[data-testid="ojt-setup-form"]').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[data-testid="registration-step-1"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="field-error-email"]').text()).toContain('An account already exists with this email.')
    expect(wrapper.get('#register-email').element.value).toBe('new@example.com')
  })
})

async function fillAccount(wrapper) {
  await wrapper.get('#register-first-name').setValue('New')
  await wrapper.get('#register-last-name').setValue('Student')
  await wrapper.get('#register-suffix').setValue('Jr.')
  await wrapper.get('#register-email').setValue('new@example.com')
  await wrapper.get('#register-password').setValue('StrongPassword1!')
  await wrapper.get('#register-password-confirmation').setValue('StrongPassword1!')
}

async function chooseDate(wrapper, fieldId, value) {
  await wrapper.get(`#${fieldId}`).trigger('click')

  for (let attempt = 0; attempt < 120; attempt += 1) {
    const day = document.body.querySelector(`[data-testid="calendar-day-${value}"]`)
    if (day && !day.disabled) {
      day.click()
      await flushPromises()
      return
    }

    const calendar = document.body.querySelector(`[data-testid="${fieldId}-calendar"]`)
    const current = new Date(`${calendar.querySelector('[aria-live]').textContent} 1`)
    const target = new Date(`${value}T00:00:00`)
    const control = target < current
      ? calendar.querySelector(`[data-testid="${fieldId}-previous"]`)
      : calendar.querySelector(`[data-testid="${fieldId}-next"]`)
    control.click()
    await flushPromises()
  }

  throw new Error(`Unable to select ${value}`)
}
