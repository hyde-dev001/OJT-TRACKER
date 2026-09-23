import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import PaginationControls from './PaginationControls.vue'

describe('PaginationControls', () => {
  it('shows the item summary and emits the selected page', async () => {
    const wrapper = mount(PaginationControls, {
      props: {
        meta: { current_page: 2, last_page: 3, per_page: 10, total: 24, from: 11, to: 20 },
      },
    })

    expect(wrapper.text()).toContain('Showing 11–20 of 24')
    await wrapper.get('[data-page="3"]').trigger('click')
    expect(wrapper.emitted('change')[0]).toEqual([3])
  })

  it('does not render controls for a single page', () => {
    const wrapper = mount(PaginationControls, {
      props: { meta: { current_page: 1, last_page: 1, per_page: 10, total: 2, from: 1, to: 2 } },
    })

    expect(wrapper.find('[data-page="1"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Showing 1–2 of 2')
  })
})
