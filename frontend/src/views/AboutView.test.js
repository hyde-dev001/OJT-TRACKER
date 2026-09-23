import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import AboutView from './AboutView.vue'

describe('AboutView', () => {
  it('explains the tracker and gives students a clear next step', () => {
    const wrapper = mount(AboutView, {
      global: {
        stubs: {
          RouterLink: { template: '<a><slot /></a>' },
        },
      },
    })

    expect(wrapper.text()).toContain('Built for student interns')
    expect(wrapper.text()).toContain('not a replacement for official attendance records')
    expect(wrapper.get('[data-testid="about-benefits"]').findAll('article')).toHaveLength(3)
    expect(wrapper.text()).toContain('Create your account')
    expect(wrapper.get('[data-testid="about-benefits"]').classes()).toContain('motion-revealed')
  })
})
