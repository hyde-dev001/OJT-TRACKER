import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import InternRequirementsView from './InternRequirementsView.vue'
import { downloadInternRequirementProof, listInternRequirements, submitInternRequirementProof } from '../../services/ojt'

vi.mock('../../services/ojt', () => ({
  downloadInternRequirementProof: vi.fn(),
  listInternRequirements: vi.fn(),
  submitInternRequirementProof: vi.fn(),
}))

vi.mock('../../services/api', () => ({
  apiErrorMessage: vi.fn(() => 'Unable to reach the API.'),
}))

const requirement = {
  id: 8,
  title: 'Submit weekly journal',
  description: 'Include a summary of work completed.',
  due_date: '2026-10-21',
  is_required: true,
  status: 'incomplete',
  submission_status: 'not_submitted',
  submission_comment: null,
  review_comment: null,
  proof_available: false,
}
const page = (items = []) => ({
  items,
  meta: { current_page: 1, last_page: 1, per_page: 10, total: items.length, from: items.length ? 1 : null, to: items.length || null },
})
const selectFile = async (wrapper, selector, file) => {
  const input = wrapper.get(selector)
  Object.defineProperty(input.element, 'files', { configurable: true, value: [file] })
  await input.trigger('change')
}

const mountView = () => mount(InternRequirementsView, {
  global: {
    stubs: {
      PageHeader: { props: ['title', 'description'], template: '<header><h1>{{ title }}</h1><p>{{ description }}</p></header>' },
      PaginationControls: true,
      StatusBadge: { props: ['status'], template: '<span>{{ status }}</span>' },
    },
  },
})

describe('InternRequirementsView', () => {
  beforeEach(() => vi.resetAllMocks())

  it('shows assigned requirement details and required status', async () => {
    listInternRequirements.mockResolvedValueOnce(page([requirement]))
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.text()).toContain('Submit weekly journal')
    expect(wrapper.text()).toContain('Required')
    expect(wrapper.text()).toContain('incomplete')
    expect(wrapper.get('[data-testid="submit-requirement-proof-8"]').text()).toContain('Submit proof')
    expect(wrapper.find('[data-testid="edit-requirement-8"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="delete-requirement-8"]').exists()).toBe(false)
  })

  it('submits a single image or PDF with an optional comment', async () => {
    listInternRequirements.mockResolvedValue(page([requirement]))
    submitInternRequirementProof.mockResolvedValueOnce({ ...requirement, submission_status: 'pending_review' })
    const wrapper = mountView()
    await flushPromises()

    const input = wrapper.get('#requirement-proof-8')
    expect(input.attributes('accept')).toContain('image/png')
    expect(input.attributes('multiple')).toBeUndefined()
    const file = new File(['proof'], 'journal.png', { type: 'image/png' })
    await selectFile(wrapper, '#requirement-proof-8', file)
    await wrapper.get('#requirement-comment-8').setValue('Journal attached.')
    await wrapper.get('[data-testid="submit-requirement-proof-8"]').trigger('submit')
    await flushPromises()

    const payload = submitInternRequirementProof.mock.calls[0][1]
    expect(submitInternRequirementProof).toHaveBeenCalledWith(8, expect.any(FormData))
    expect(payload.get('proof')).toBe(file)
    expect(payload.get('comment')).toBe('Journal attached.')
    expect(wrapper.text()).toContain('Proof submitted for review')
  })

  it('requires a file and rejects unsupported or oversized files before upload', async () => {
    listInternRequirements.mockResolvedValue(page([requirement]))
    const wrapper = mountView()
    await flushPromises()

    await wrapper.get('[data-testid="submit-requirement-proof-8"]').trigger('submit')
    expect(wrapper.get('[data-testid="requirement-proof-error-8"]').text()).toContain('Choose an image or PDF')
    await selectFile(wrapper, '#requirement-proof-8', new File(['bad'], 'proof.txt', { type: 'text/plain' }))
    expect(wrapper.get('[data-testid="requirement-proof-error-8"]').text()).toContain('Choose an image or PDF')
    const oversized = new File([new ArrayBuffer(10 * 1024 * 1024 + 1)], 'large.pdf', { type: 'application/pdf' })
    await selectFile(wrapper, '#requirement-proof-8', oversized)
    expect(wrapper.get('[data-testid="requirement-proof-error-8"]').text()).toContain('10 MB')
    expect(submitInternRequirementProof).not.toHaveBeenCalled()
  })

  it('keeps approved requirements read-only', async () => {
    listInternRequirements.mockResolvedValueOnce(page([{ ...requirement, status: 'completed', submission_status: 'approved' }]))
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.text()).toContain('Approved')
    expect(wrapper.find('[data-testid="submit-requirement-proof-8"]').exists()).toBe(false)
  })

  it('keeps a pending submission read-only until the company reviews it', async () => {
    listInternRequirements.mockResolvedValueOnce(page([{ ...requirement, submission_status: 'pending_review' }]))
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.get('[data-testid="intern-requirement-8"]').text()).toContain('waiting for review')
    expect(wrapper.find('[data-testid="submit-requirement-proof-8"]').exists()).toBe(false)
  })

  it('shows a recoverable error when the requirements cannot load', async () => {
    listInternRequirements.mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce(page([]))
    const wrapper = mountView()
    await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).toContain('Unable to reach the API')

    await wrapper.get('[data-testid="retry-intern-requirements"]').trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('No assigned requirements')
  })
})
