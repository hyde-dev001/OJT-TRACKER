import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import AppModal from './AppModal.vue'

describe('AppModal', () => {
  it('renders an accessible dialog and closes on Escape', async () => {
    const wrapper = mount(AppModal, {
      props: { open: true, title: 'Edit work log', description: 'Update the schedule.' },
      slots: { default: '<p>Form content</p>' },
      attachTo: document.body,
    })

    const dialog = document.body.querySelector('[role="dialog"]')
    expect(dialog?.getAttribute('aria-modal')).toBe('true')
    expect(dialog?.textContent).toContain('Edit work log')
    expect(dialog?.textContent).toContain('Form content')

    dialog.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await nextTick()
    expect(wrapper.emitted('close')).toHaveLength(1)
    wrapper.unmount()
  })

  it('does not close while busy', async () => {
    const wrapper = mount(AppModal, {
      props: { open: true, title: 'Saving', busy: true },
      attachTo: document.body,
    })

    document.body.querySelector('[role="dialog"]').dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await nextTick()
    expect(wrapper.emitted('close')).toBeUndefined()
    wrapper.unmount()
  })

  it('keeps keyboard focus inside the dialog', async () => {
    const wrapper = mount(AppModal, {
      props: { open: true, title: 'Keyboard test' },
      slots: { default: '<button data-first>First</button><button data-last>Last</button>' },
      attachTo: document.body,
    })

    const dialog = document.body.querySelector('[role="dialog"]')
    const first = document.body.querySelector('[data-first]')
    const last = document.body.querySelector('[data-last]')

    dialog.focus()
    dialog.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true }))
    expect(document.activeElement).toBe(first)

    first.focus()
    dialog.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true }))
    expect(document.activeElement).toBe(last)
    wrapper.unmount()
  })
})
