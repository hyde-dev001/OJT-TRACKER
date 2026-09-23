import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import EmptyState from './EmptyState.vue'

describe('EmptyState', () => {
  it('shows a helpful message and action slot', () => {
    const wrapper = mount(EmptyState, {
      props: { title: 'No work logs yet', message: 'Add your first work log.' },
      slots: { action: '<button>Add work log</button>' },
    })

    expect(wrapper.text()).toContain('Add your first work log.')
    expect(wrapper.get('button').text()).toBe('Add work log')
  })
})
