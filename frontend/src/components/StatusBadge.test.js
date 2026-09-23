import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import StatusBadge from './StatusBadge.vue'

describe('StatusBadge', () => {
  it('renders a readable label for a completed status', () => {
    const wrapper = mount(StatusBadge, { props: { status: 'completed' } })

    expect(wrapper.get('[role="status"]').text()).toBe('Completed')
  })

  it.each([
    ['not_started', 'Not started'],
    ['on_track', 'On track'],
    ['at_risk', 'At risk'],
    ['complete', 'Complete'],
    ['deadline_passed', 'Deadline passed'],
    ['pace_unavailable', 'Pace unavailable'],
    ['ready', 'Ready'],
    ['not_ready', 'Not ready'],
  ])('renders a readable assistant label for %s', (status, label) => {
    const wrapper = mount(StatusBadge, { props: { status } })

    expect(wrapper.get('[role="status"]').text()).toBe(label)
  })
})
