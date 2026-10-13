import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import CompanyAccountView from './CompanyAccountView.vue'
import { changeCompanyPassword } from '../../services/ojt'

vi.mock('../../services/ojt', () => ({
  changeCompanyPassword: vi.fn(),
}))

vi.mock('../../services/api', () => ({
  apiErrorMessage: vi.fn(() => 'Unable to change your password.'),
}))

describe('CompanyAccountView', () => {
  beforeEach(() => vi.resetAllMocks())

  it('lets the company account holder change their password and confirms the result', async () => {
    changeCompanyPassword.mockResolvedValueOnce({})
    const wrapper = mount(CompanyAccountView)

    await wrapper.get('#company-current-password').setValue('CurrentPassword1!')
    await wrapper.get('#company-new-password').setValue('NewCompanyPassword2!')
    await wrapper.get('#company-password-confirmation').setValue('NewCompanyPassword2!')
    await wrapper.get('[data-testid="company-password-form"]').trigger('submit')
    await flushPromises()

    expect(changeCompanyPassword).toHaveBeenCalledWith({
      current_password: 'CurrentPassword1!',
      password: 'NewCompanyPassword2!',
      password_confirmation: 'NewCompanyPassword2!',
    })
    expect(document.body.textContent).toContain('Password changed')
    wrapper.unmount()
  })
})
