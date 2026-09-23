import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { describe, expect, it } from 'vitest'
import ActionAlert from './ActionAlert.vue'

describe('ActionAlert', () => {
  it('shows the action result in a modal and emits close', async () => {
    const wrapper = mount(ActionAlert, {
      props: {
        open: true,
        variant: 'success',
        title: 'Work log saved',
        message: 'Your completed work log was saved.',
      },
    })

    expect(document.body.querySelector('[data-testid="action-alert"]')).not.toBeNull()
    expect(document.body.textContent).toContain('Work log saved')
    expect(document.body.textContent).toContain('Your completed work log was saved.')

    document.body.querySelector('[data-action="alert-close"]').click()
    expect(wrapper.emitted('close')).toHaveLength(1)
    wrapper.unmount()
  })

  it('returns focus to the triggering control after closing', async () => {
    const trigger = document.createElement('button')
    document.body.append(trigger)
    trigger.focus()
    const wrapper = mount(ActionAlert, {
      props: { open: false, title: 'Saved successfully', message: 'Done.' },
    })

    await wrapper.setProps({ open: true })
    document.body.querySelector('[data-action="alert-close"]').click()
    await wrapper.setProps({ open: false })
    await nextTick()

    expect(document.activeElement).toBe(trigger)
    trigger.remove()
    wrapper.unmount()
  })
})
