import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import StudentAccountMenu from './StudentAccountMenu.vue'

const RouterLinkStub = {
  props: ['to'],
  emits: ['click'],
  template: '<a :href="to" @click.prevent="$emit(\'click\', $event)"><slot /></a>',
}

const user = {
  id: 1,
  name: 'John Daniel Paragas',
  email: 'student@example.com',
}

describe('StudentAccountMenu', () => {
  it('opens with account details and closes after choosing a profile link', async () => {
    const wrapper = mount(StudentAccountMenu, {
      props: { user },
      global: { stubs: { RouterLink: RouterLinkStub } },
    })

    await wrapper.get('[data-testid="account-trigger"]').trigger('click')

    expect(wrapper.get('[data-testid="account-trigger"]').attributes('aria-expanded')).toBe('true')
    expect(wrapper.get('[data-testid="account-avatar"]').text()).toBe('JD')
    expect(wrapper.get('[data-testid="account-menu"]').text()).toContain('John Daniel Paragas')
    expect(wrapper.get('[data-testid="account-menu"]').text()).toContain('student@example.com')
    expect(wrapper.find('.account-role-badge').exists()).toBe(false)
    expect(wrapper.get('[data-testid="account-profile"]').attributes('href')).toBe('/student/profile')

    await wrapper.get('[data-testid="account-profile"]').trigger('click')
    expect(wrapper.find('[data-testid="account-menu"]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('closes on outside click and Escape, and emits sign out', async () => {
    const wrapper = mount(StudentAccountMenu, {
      props: { user },
      global: { stubs: { RouterLink: RouterLinkStub } },
    })

    await wrapper.get('[data-testid="account-trigger"]').trigger('click')
    document.dispatchEvent(new Event('pointerdown'))
    await nextTick()
    expect(wrapper.find('[data-testid="account-menu"]').exists()).toBe(false)

    await wrapper.get('[data-testid="account-trigger"]').trigger('click')
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await nextTick()
    expect(wrapper.find('[data-testid="account-menu"]').exists()).toBe(false)

    await wrapper.get('[data-testid="account-trigger"]').trigger('click')
    await wrapper.get('[data-testid="account-sign-out"]').trigger('click')
    expect(wrapper.emitted('sign-out')).toHaveLength(1)
    expect(wrapper.find('[data-testid="account-menu"]').exists()).toBe(false)

    wrapper.unmount()
  })
})
