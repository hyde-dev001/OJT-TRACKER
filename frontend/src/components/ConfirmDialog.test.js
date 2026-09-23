import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ConfirmDialog from './ConfirmDialog.vue'

describe('ConfirmDialog', () => {
  it('renders explicit cancel and confirm actions', async () => {
    const wrapper = mount(ConfirmDialog, {
      props: {
        open: true,
        title: 'Complete work log?',
        message: 'Completed logs count toward tracked hours.',
        confirmLabel: 'Complete',
      },
    })

    expect(document.body.querySelector('[data-action="cancel"]').textContent).toBe('Cancel')
    expect(document.body.querySelector('[data-action="confirm"]').textContent).toBe('Complete')
    document.body.querySelector('[data-action="cancel"]').click()
    document.body.querySelector('[data-action="confirm"]').click()
    await nextTick()
    expect(wrapper.emitted('cancel')).toHaveLength(1)
    expect(wrapper.emitted('confirm')).toHaveLength(1)
    wrapper.unmount()
  })

  it('disables actions while busy', () => {
    const wrapper = mount(ConfirmDialog, {
      props: { open: true, title: 'Signing out', message: 'Please wait.', busy: true, busyLabel: 'Signing out…' },
    })

    expect(document.body.querySelector('[data-action="cancel"]').hasAttribute('disabled')).toBe(true)
    expect(document.body.querySelector('[data-action="confirm"]').hasAttribute('disabled')).toBe(true)
    expect(document.body.querySelector('[data-action="confirm"]').textContent).toBe('Signing out…')
    wrapper.unmount()
  })
})
