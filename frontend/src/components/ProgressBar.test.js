import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ProgressBar from './ProgressBar.vue'

describe('ProgressBar', () => {
  it('displays progress labels and exposes an accessible value', () => {
    const wrapper = mount(ProgressBar, {
      props: {
        percentage: 68,
        completedLabel: '340 hours completed',
        requiredLabel: '500 hours required',
        remainingLabel: '160 hours remaining',
      },
    })

    expect(wrapper.text()).toContain('340 hours completed')
    expect(wrapper.text()).toContain('500 hours required')
    expect(wrapper.text()).toContain('160 hours remaining')
    expect(wrapper.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('68')
  })
})
