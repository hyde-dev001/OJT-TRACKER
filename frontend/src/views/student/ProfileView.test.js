import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia } from 'pinia'
import ProfileView from './ProfileView.vue'
import { useAuthStore } from '../../stores/auth'
import api, { ensureCsrfCookie } from '../../services/api'
import { getStudentInternship } from '../../services/ojt'

vi.mock('../../services/api', () => ({
  default: { put: vi.fn() },
  ensureCsrfCookie: vi.fn(),
  apiErrorMessage: vi.fn(() => 'Unable to save your profile. Please try again.'),
}))

vi.mock('../../services/ojt', () => ({
  getStudentInternship: vi.fn(),
}))

describe('ProfileView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('loads and saves the student profile with popup feedback', async () => {
    getStudentInternship.mockResolvedValue({
      start_date: '2026-09-22',
      end_date: '2026-12-22',
      work_days: [1, 3, 5],
      expected_hours_per_day: 7.5,
    })
    ensureCsrfCookie.mockResolvedValue({})
    api.put.mockResolvedValue({
      data: { user: { id: 1, name: 'Updated Student', email: 'student@example.com' } },
    })
    const pinia = createPinia()
    const auth = useAuthStore(pinia)
    auth.user = { id: 1, name: 'Student', email: 'student@example.com' }

    const wrapper = mount(ProfileView, { global: { plugins: [pinia] } })
    await flushPromises()

    expect(wrapper.get('#profile-email').element.value).toBe('student@example.com')
    expect(wrapper.get('#profile-start-date').element.value).toBe('Sep 22, 2026')
    expect(wrapper.get('#profile-end-date').element.value).toBe('Dec 22, 2026')
    expect(wrapper.get('input[aria-label="Mon"]').element.checked).toBe(true)
    expect(wrapper.get('input[aria-label="Tue"]').element.checked).toBe(false)
    expect(wrapper.get('#profile-expected-hours-per-day').element.value).toBe('7.5')
    expect(wrapper.get('[data-testid="profile-password-rules"]').exists()).toBe(true)

    for (const [testId, inputId] of [
      ['toggle-current-password', '#profile-current-password'],
      ['toggle-new-password', '#profile-password'],
      ['toggle-confirm-password', '#profile-password-confirmation'],
    ]) {
      expect(wrapper.get(inputId).element.type).toBe('password')
      await wrapper.get(`[data-testid="${testId}"]`).trigger('click')
      expect(wrapper.get(inputId).element.type).toBe('text')
      await wrapper.get(`[data-testid="${testId}"]`).trigger('click')
    }

    await wrapper.get('#profile-name').setValue('Updated Student')
    await chooseDate(wrapper, 'profile-start-date', '2026-10-01')
    await chooseDate(wrapper, 'profile-end-date', '2027-01-01')
    await wrapper.get('input[aria-label="Tue"]').setValue(true)
    await wrapper.get('#profile-current-password').setValue('secret-password')
    await wrapper.get('#profile-password').setValue('NewStrongPassword1!')
    await wrapper.get('#profile-password-confirmation').setValue('NewStrongPassword1!')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(api.put).toHaveBeenCalledWith('/profile', {
      name: 'Updated Student',
      start_date: '2026-10-01',
      end_date: '2027-01-01',
      work_days: [1, 2, 3, 5],
      expected_hours_per_day: 7.5,
      current_password: 'secret-password',
      password: 'NewStrongPassword1!',
      password_confirmation: 'NewStrongPassword1!',
    })
    expect(document.body.textContent).toContain('Password updated')
    expect(auth.user.name).toBe('Updated Student')
  })

  it('shows a popup when a password change fails', async () => {
    getStudentInternship.mockResolvedValue({ start_date: '2026-09-22', end_date: '2026-12-22' })
    ensureCsrfCookie.mockResolvedValue({})
    api.put.mockRejectedValue({
      response: { status: 422, data: { errors: { current_password: ['The password is incorrect.'] } } },
    })
    const pinia = createPinia()
    const auth = useAuthStore(pinia)
    auth.user = { id: 1, name: 'Student', email: 'student@example.com' }

    const wrapper = mount(ProfileView, { global: { plugins: [pinia] } })
    await flushPromises()
    await wrapper.get('#profile-current-password').setValue('wrong-password')
    await wrapper.get('#profile-password').setValue('NewStrongPassword1!')
    await wrapper.get('#profile-password-confirmation').setValue('NewStrongPassword1!')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(document.body.querySelector('[data-testid="action-alert"]')).not.toBeNull()
    expect(document.body.textContent).toContain('Password not changed')
    wrapper.unmount()
  })
})

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
