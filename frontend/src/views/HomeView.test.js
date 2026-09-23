import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import HomeView from './HomeView.vue'

describe('HomeView', () => {
  const mountHome = () => mount(HomeView, {
    global: {
      stubs: {
        RouterLink: { template: '<a><slot /></a>' },
      },
    },
  })

  it('keeps the public landing page focused on the student workflow', () => {
    const wrapper = mountHome()

    expect(wrapper.text()).toContain('Track your OJT progress with clarity.')
    expect(wrapper.text()).toContain('Create student account')
    expect(wrapper.get('[data-testid="home-progress-preview"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="home-feature-grid"]').text()).toContain('Work hours')
    expect(wrapper.text()).not.toContain('Connected to the tracker API')
    expect(wrapper.text()).not.toContain('Unable to connect to the Laravel API.')
  })

  it('keeps the example progress complete when reduced motion is preferred', async () => {
    Object.defineProperty(window, 'matchMedia', {
      configurable: true,
      value: vi.fn(() => ({
        matches: true,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
      })),
    })

    const wrapper = mountHome()
    await flushPromises()

    const preview = wrapper.get('[data-testid="home-progress-preview"]')
    expect(preview.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('68')
    expect(preview.text()).toContain('340')
    expect(preview.text()).toContain('160 hours remaining')
    expect(wrapper.get('[data-testid="home-feature-grid"]').classes()).toContain('motion-revealed')
  })
})
