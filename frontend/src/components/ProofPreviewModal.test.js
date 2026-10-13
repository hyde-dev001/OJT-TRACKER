import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import ProofPreviewModal from './ProofPreviewModal.vue'

describe('ProofPreviewModal', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('previews PDFs and revokes their temporary URL when closed', async () => {
    const createObjectURL = vi.fn(() => 'blob:proof')
    const revokeObjectURL = vi.fn()
    vi.stubGlobal('URL', { createObjectURL, revokeObjectURL })
    const wrapper = mount(ProofPreviewModal, {
      props: { open: true, title: 'Task proof', file: new Blob(['%PDF'], { type: 'application/pdf' }) },
    })

    expect(document.body.querySelector('iframe')?.getAttribute('src')).toBe('blob:proof')
    document.body.querySelector('[data-testid="close-proof-preview"]').click()
    expect(wrapper.emitted('close')).toHaveLength(1)
    await wrapper.setProps({ open: false })
    await nextTick()
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:proof')
    wrapper.unmount()
  })
})
