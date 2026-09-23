import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import PageHeader from './PageHeader.vue'

describe('PageHeader', () => {
  it('renders a page title, description, and optional action', () => {
    const wrapper = mount(PageHeader, {
      props: { title: 'Work Hours', description: 'Track completed progress.' },
      slots: { action: '<button>Add</button>' },
    })

    expect(wrapper.get('h1').text()).toBe('Work Hours')
    expect(wrapper.text()).toContain('Track completed progress.')
    expect(wrapper.get('button').text()).toBe('Add')
  })
})
