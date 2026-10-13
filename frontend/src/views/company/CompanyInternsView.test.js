import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import CompanyInternsView from './CompanyInternsView.vue'
import {
  createCompanyIntern,
  listCompanyInterns,
  resetCompanyInternPassword,
  updateCompanyPlacement,
} from '../../services/ojt'

vi.mock('../../services/ojt', () => ({
  createCompanyIntern: vi.fn(),
  listCompanyInterns: vi.fn(),
  resetCompanyInternPassword: vi.fn(),
  updateCompanyPlacement: vi.fn(),
}))

const DatePickerFieldStub = {
  props: ['id', 'modelValue', 'invalid'],
  emits: ['update:modelValue'],
  template: '<input :id="id" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)">',
}

const intern = {
  id: 7,
  name: 'Taylor Lee',
  first_name: 'Taylor',
  last_name: 'Lee',
  email: 'taylor.lee@example.com',
  setup_complete: false,
  start_date: '2026-11-01',
  end_date: '2027-02-28',
  work_days: [1, 2, 3, 4, 5],
  expected_hours_per_day: 8,
}

const mountView = () => mount(CompanyInternsView, {
  global: { stubs: { DatePickerField: DatePickerFieldStub } },
})

describe('CompanyInternsView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('shows a loading state, then an empty state', async () => {
    let resolveList
    listCompanyInterns.mockReturnValue(new Promise((resolve) => { resolveList = resolve }))
    const wrapper = mountView()

    expect(wrapper.text()).toContain('Loading intern accounts')

    resolveList([])
    await flushPromises()

    expect(wrapper.text()).toContain('No intern accounts yet')
  })

  it('shows a recoverable error when the intern list cannot load', async () => {
    listCompanyInterns.mockRejectedValueOnce(new Error('offline'))
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('The API is unavailable')

    listCompanyInterns.mockResolvedValueOnce([])
    await wrapper.get('[data-testid="retry-company-interns"]').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('No intern accounts yet')
  })

  it('creates an intern, displays its setup state, and clears the temporary password', async () => {
    listCompanyInterns.mockResolvedValueOnce([])
    createCompanyIntern.mockResolvedValueOnce(intern)
    const wrapper = mountView()
    await flushPromises()
    await fillCreateForm(wrapper)
    await wrapper.get('[data-testid="company-intern-create-form"]').trigger('submit')
    await flushPromises()

    expect(createCompanyIntern).toHaveBeenCalledWith({
      first_name: 'Taylor',
      last_name: 'Lee',
      email: 'taylor.lee@example.com',
      password: 'TemporaryPass1!',
      password_confirmation: 'TemporaryPass1!',
      start_date: '2026-11-01',
      end_date: '2027-02-28',
      work_days: [1, 2, 3, 4, 5],
      expected_hours_per_day: 8,
    })
    expect(wrapper.text()).toContain('Needs initial setup')
    expect(wrapper.text()).toContain('Intern account created')
    expect(wrapper.get('#new-intern-password').element.value).toBe('')
    expect(wrapper.get('#new-intern-password-confirmation').element.value).toBe('')
  })

  it('shows server field validation errors and keeps the intern details', async () => {
    listCompanyInterns.mockResolvedValueOnce([])
    createCompanyIntern.mockRejectedValueOnce({
      response: { status: 422, data: { errors: { email: ['An account already exists with this email.'] } } },
    })
    const wrapper = mountView()
    await flushPromises()
    await fillCreateForm(wrapper)
    await wrapper.get('[data-testid="company-intern-create-form"]').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[data-testid="new-intern-email-error"]').text()).toContain('An account already exists')
    expect(wrapper.get('#new-intern-email').element.value).toBe('taylor.lee@example.com')
  })

  it('updates placement dates and schedule for the selected intern', async () => {
    listCompanyInterns.mockResolvedValueOnce([intern])
    updateCompanyPlacement.mockResolvedValueOnce({ ...intern, start_date: '2026-11-02', expected_hours_per_day: 7.5 })
    const wrapper = mountView()
    await flushPromises()
    await wrapper.get('[data-testid="edit-placement-7"]').trigger('click')
    await wrapper.get('#placement-start-date-7').setValue('2026-11-02')
    await wrapper.get('#placement-end-date-7').setValue('2027-03-01')
    await wrapper.get('#placement-expected-hours-7').setValue('7.5')
    await wrapper.get('[data-testid="placement-form-7"]').trigger('submit')
    await flushPromises()

    expect(updateCompanyPlacement).toHaveBeenCalledWith(7, {
      start_date: '2026-11-02',
      end_date: '2027-03-01',
      work_days: [1, 2, 3, 4, 5],
      expected_hours_per_day: 7.5,
    })
    expect(wrapper.text()).toContain('Placement updated')
  })

  it('resets an intern password and clears the reset password fields', async () => {
    listCompanyInterns.mockResolvedValueOnce([intern])
    resetCompanyInternPassword.mockResolvedValueOnce({ ...intern, setup_complete: false })
    const wrapper = mountView()
    await flushPromises()
    await wrapper.get('[data-testid="reset-password-7"]').trigger('click')
    await wrapper.get('#reset-password-7').setValue('NewTemporaryPass1!')
    await wrapper.get('#reset-password-confirmation-7').setValue('NewTemporaryPass1!')
    await wrapper.get('[data-testid="reset-password-form-7"]').trigger('submit')
    await flushPromises()

    expect(resetCompanyInternPassword).toHaveBeenCalledWith(7, {
      password: 'NewTemporaryPass1!',
      password_confirmation: 'NewTemporaryPass1!',
    })
    expect(wrapper.text()).toContain('Temporary password reset')
    expect(wrapper.text()).not.toContain('NewTemporaryPass1!')
    expect(wrapper.find('#reset-password-7').exists()).toBe(false)
  })
})

async function fillCreateForm(wrapper) {
  await wrapper.get('#new-intern-first-name').setValue('Taylor')
  await wrapper.get('#new-intern-last-name').setValue('Lee')
  await wrapper.get('#new-intern-email').setValue('taylor.lee@example.com')
  await wrapper.get('#new-intern-password').setValue('TemporaryPass1!')
  await wrapper.get('#new-intern-password-confirmation').setValue('TemporaryPass1!')
  await wrapper.get('#new-intern-start-date').setValue('2026-11-01')
  await wrapper.get('#new-intern-end-date').setValue('2027-02-28')
}
