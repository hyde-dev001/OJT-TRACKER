import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia } from 'pinia'
import CompanyRegisterView from './CompanyRegisterView.vue'
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

describe('CompanyRegisterView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('creates a company account and opens its intern workspace', async () => {
    ensureCsrfCookie.mockResolvedValue({})
    api.post.mockResolvedValue({
      data: { user: { id: 4, role: 'company', must_change_password: false } },
    })
    const wrapper = mount(CompanyRegisterView, { global: { plugins: [createPinia()] } })

    await wrapper.get('#company-name').setValue('Northwind Studio')
    await wrapper.get('#company-first-name').setValue('Jamie')
    await wrapper.get('#company-last-name').setValue('Santos')
    await wrapper.get('#company-email').setValue('owner@northwind.example')
    await wrapper.get('#company-password').setValue('StrongPassword1!')
    await wrapper.get('#company-password-confirmation').setValue('StrongPassword1!')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith('/company/register', {
      company_name: 'Northwind Studio',
      first_name: 'Jamie',
      last_name: 'Santos',
      email: 'owner@northwind.example',
      password: 'StrongPassword1!',
      password_confirmation: 'StrongPassword1!',
    })
    expect(ensureCsrfCookie).toHaveBeenCalledOnce()
    expect(routerPush).toHaveBeenCalledWith('/company/interns')
  })

  it('shows company registration field errors and preserves the form', async () => {
    ensureCsrfCookie.mockResolvedValue({})
    api.post.mockRejectedValue({
      response: { status: 422, data: { errors: { email: ['An account already exists with this email.'] } } },
    })
    const wrapper = mount(CompanyRegisterView, { global: { plugins: [createPinia()] } })

    await wrapper.get('#company-name').setValue('Northwind Studio')
    await wrapper.get('#company-first-name').setValue('Jamie')
    await wrapper.get('#company-last-name').setValue('Santos')
    await wrapper.get('#company-email').setValue('owner@northwind.example')
    await wrapper.get('#company-password').setValue('StrongPassword1!')
    await wrapper.get('#company-password-confirmation').setValue('StrongPassword1!')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[data-testid="company-email-error"]').text()).toContain('An account already exists')
    expect(wrapper.get('#company-email').element.value).toBe('owner@northwind.example')
  })
})
