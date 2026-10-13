import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import InternAccountView from './InternAccountView.vue'
import api, { ensureCsrfCookie } from '../../services/api'
import { useAuthStore } from '../../stores/auth'

enableAutoUnmount(afterEach)

vi.mock('../../services/api', () => ({
  default: { get: vi.fn(), post: vi.fn(), put: vi.fn() },
  ensureCsrfCookie: vi.fn(),
  apiErrorMessage: vi.fn(() => 'Please correct the highlighted fields.'),
}))

const internship = {
  id: 21,
  required_minutes: null,
  required_hours: null,
  start_date: '2026-11-02',
  end_date: '2027-02-28',
  work_days: [1, 3, 5],
  expected_daily_minutes: 450,
  expected_hours_per_day: 7.5,
  progress: {
    completed_minutes: 0,
    completed_hours: 0,
    required_minutes: null,
    required_hours: null,
    remaining_minutes: null,
    remaining_hours: null,
    percentage: null,
  },
}

describe('InternAccountView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
    ensureCsrfCookie.mockResolvedValue({})
  })

  it('requires a password change before requesting the internship', async () => {
    api.put.mockResolvedValue({
      data: { user: { id: 8, role: 'intern', must_change_password: false } },
    })
    api.get.mockResolvedValue({ data: { data: internship } })
    const { wrapper, auth } = mountInternAccount({ must_change_password: true })

    expect(wrapper.text()).toContain('Change your temporary password')
    expect(api.get).not.toHaveBeenCalled()

    await wrapper.get('#intern-current-password').setValue('TemporaryPass1!')
    await wrapper.get('#intern-new-password').setValue('PermanentPassword2!')
    await wrapper.get('#intern-password-confirmation').setValue('PermanentPassword2!')
    await wrapper.get('[data-testid="intern-password-form"]').trigger('submit')
    await flushPromises()

    expect(api.put).toHaveBeenCalledWith('/intern/password', {
      current_password: 'TemporaryPass1!',
      password: 'PermanentPassword2!',
      password_confirmation: 'PermanentPassword2!',
    })
    expect(api.put.mock.invocationCallOrder[0]).toBeLessThan(api.get.mock.invocationCallOrder[0])
    expect(api.get).toHaveBeenCalledWith('/intern/internship')
    expect(auth.user.must_change_password).toBe(false)
    expect(wrapper.text()).toContain('Enter the total number of hours your school requires')
    expect(wrapper.text()).toContain('2026-11-02')
  })

  it('updates only required hours and shows placement values read-only', async () => {
    api.get.mockResolvedValue({ data: { data: { ...internship, required_minutes: 30000, required_hours: 500 } } })
    api.put.mockResolvedValue({
      data: { data: { ...internship, required_minutes: 36000, required_hours: 600 } },
    })
    const { wrapper } = mountInternAccount({ must_change_password: false })
    await flushPromises()

    expect(wrapper.text()).toContain('2026-11-02')
    expect(wrapper.text()).toContain('Mon, Wed, Fri')
    expect(wrapper.text()).toContain('7.5 hours')
    expect(wrapper.find('input#placement-start-date').exists()).toBe(false)

    await wrapper.get('#required-ojt-hours').setValue('600')
    await wrapper.get('[data-testid="required-hours-form"]').trigger('submit')
    await flushPromises()

    expect(api.put).toHaveBeenCalledWith('/intern/internship', { required_hours: 600 })
    expect(document.body.textContent).toContain('OJT hours saved')
  })

  it('lets interns show and hide each password during OJT account setup', async () => {
    api.get.mockResolvedValue({ data: { data: internship } })
    const { wrapper } = mountInternAccount({ must_change_password: true })

    const fields = [
      '#intern-current-password',
      '#intern-new-password',
      '#intern-password-confirmation',
    ]

    for (const selector of fields) {
      expect(wrapper.get(selector).attributes('type')).toBe('password')
    }

    await wrapper.get('[data-testid="show-intern-current-password"]').trigger('click')
    expect(wrapper.get('#intern-current-password').attributes('type')).toBe('text')
    await wrapper.get('[data-testid="show-intern-current-password"]').trigger('click')
    expect(wrapper.get('#intern-current-password').attributes('type')).toBe('password')

    await wrapper.get('[data-testid="show-intern-new-password"]').trigger('click')
    expect(wrapper.get('#intern-new-password').attributes('type')).toBe('text')
    await wrapper.get('[data-testid="show-intern-confirm-password"]').trigger('click')
    expect(wrapper.get('#intern-password-confirmation').attributes('type')).toBe('text')
  })

  it('shows the required-hours validation error without losing the entered value', async () => {
    api.get.mockResolvedValue({ data: { data: internship } })
    api.put.mockRejectedValueOnce({
      response: { status: 422, data: { errors: { required_hours: ['Required OJT hours must be a whole number from 1 to 10,000.'] } } },
    })
    const { wrapper } = mountInternAccount({ must_change_password: false })
    await flushPromises()

    await wrapper.get('#required-ojt-hours').setValue('0')
    await wrapper.get('[data-testid="required-hours-form"]').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[data-testid="required-hours-error"]').text()).toContain('whole number from 1 to 10,000')
    expect(wrapper.get('#required-ojt-hours').element.value).toBe('0')
  })

  it('allows a later password change after the required-hours target is set', async () => {
    api.get.mockResolvedValue({
      data: { data: { ...internship, required_minutes: 30000, required_hours: 500 } },
    })
    api.put.mockResolvedValue({
      data: { user: { id: 8, role: 'intern', must_change_password: false } },
    })
    const { wrapper } = mountInternAccount({ must_change_password: false })
    await flushPromises()

    await wrapper.get('#intern-current-password').setValue('PermanentPassword2!')
    await wrapper.get('#intern-new-password').setValue('AnotherPassword3!')
    await wrapper.get('#intern-password-confirmation').setValue('AnotherPassword3!')
    await wrapper.get('[data-testid="intern-password-form"]').trigger('submit')
    await flushPromises()

    expect(api.put).toHaveBeenCalledWith('/intern/password', {
      current_password: 'PermanentPassword2!',
      password: 'AnotherPassword3!',
      password_confirmation: 'AnotherPassword3!',
    })
    expect(document.body.textContent).toContain('Password changed')
  })
})

function mountInternAccount(overrides) {
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.user = {
    id: 8,
    name: 'Taylor Lee',
    email: 'taylor.lee@example.com',
    role: 'intern',
    must_change_password: false,
    ...overrides,
  }

  return { wrapper: mount(InternAccountView, { global: { plugins: [pinia] } }), auth }
}
