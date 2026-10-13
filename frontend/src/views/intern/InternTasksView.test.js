import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import InternTasksView from './InternTasksView.vue'
import { downloadInternTaskProof, listInternTasks, submitInternTaskProof } from '../../services/ojt'

vi.mock('../../services/ojt', () => ({
  downloadInternTaskProof: vi.fn(),
  listInternTasks: vi.fn(),
  submitInternTaskProof: vi.fn(),
}))

vi.mock('../../services/api', () => ({
  apiErrorMessage: vi.fn(() => 'Unable to reach the API.'),
}))

enableAutoUnmount(afterEach)

const task = {
  id: 4,
  title: 'Prepare report',
  description: 'Summarize assigned work.',
  due_date: '2026-10-20',
  status: 'in_progress',
  submission_status: 'changes_requested',
  submission_comment: 'First version attached.',
  review_comment: 'Please include the date range.',
  proof_available: true,
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

const mountView = () => mount(InternTasksView, {
  global: {
    stubs: {
      PageHeader: { props: ['title', 'description'], template: '<header><h1>{{ title }}</h1><p>{{ description }}</p></header>' },
      PaginationControls: true,
      StatusBadge: { props: ['status'], template: '<span>{{ status }}</span>' },
    },
  },
})

describe('InternTasksView', () => {
  beforeEach(() => vi.resetAllMocks())
  afterEach(() => vi.unstubAllGlobals())

  it('shows assigned task details, separate submission state, and requested changes', async () => {
    listInternTasks.mockResolvedValueOnce(page([task]))
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.text()).toContain('Prepare report')
    expect(wrapper.text()).toContain('in_progress')
    expect(wrapper.text()).toContain('Changes requested')
    expect(wrapper.text()).toContain('Please include the date range.')
    expect(wrapper.get('[data-testid="submit-task-proof-4"]').text()).toContain('Resubmit proof')
    expect(wrapper.find('[data-testid="edit-task-4"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="delete-task-4"]').exists()).toBe(false)
  })

  it('submits a single image or PDF with an optional comment', async () => {
    listInternTasks.mockResolvedValue(page([task]))
    submitInternTaskProof.mockResolvedValueOnce({ ...task, submission_status: 'pending_review' })
    const wrapper = mountView()
    await flushPromises()

    const input = wrapper.get('#task-proof-4')
    expect(input.attributes('accept')).toContain('application/pdf')
    expect(input.attributes('multiple')).toBeUndefined()
    const file = new File(['proof'], 'proof.pdf', { type: 'application/pdf' })
    await selectFile(wrapper, '#task-proof-4', file)
    await wrapper.get('#task-comment-4').setValue('Updated report attached.')
    await wrapper.get('[data-testid="submit-task-proof-4"]').trigger('submit')
    await flushPromises()

    const payload = submitInternTaskProof.mock.calls[0][1]
    expect(submitInternTaskProof).toHaveBeenCalledWith(4, expect.any(FormData))
    expect(payload.get('proof')).toBe(file)
    expect(payload.get('comment')).toBe('Updated report attached.')
    expect(document.body.textContent).toContain('Proof submitted')
  })

  it('requires a file and rejects unsupported or oversized files before upload', async () => {
    listInternTasks.mockResolvedValue(page([task]))
    const wrapper = mountView()
    await flushPromises()

    await wrapper.get('[data-testid="submit-task-proof-4"]').trigger('submit')
    expect(wrapper.get('[data-testid="task-proof-error-4"]').text()).toContain('Choose an image or PDF')
    await selectFile(wrapper, '#task-proof-4', new File(['bad'], 'proof.txt', { type: 'text/plain' }))
    expect(wrapper.get('[data-testid="task-proof-error-4"]').text()).toContain('Choose an image or PDF')
    const oversized = new File([new ArrayBuffer(10 * 1024 * 1024 + 1)], 'large.pdf', { type: 'application/pdf' })
    await selectFile(wrapper, '#task-proof-4', oversized)
    expect(wrapper.get('[data-testid="task-proof-error-4"]').text()).toContain('10 MB')
    expect(submitInternTaskProof).not.toHaveBeenCalled()
  })

  it('does not show submission controls after company approval', async () => {
    listInternTasks.mockResolvedValueOnce(page([{ ...task, submission_status: 'approved', status: 'completed' }]))
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.text()).toContain('Approved')
    expect(wrapper.find('[data-testid="submit-task-proof-4"]').exists()).toBe(false)
  })

  it('lets the intern preview their private proof file', async () => {
    listInternTasks.mockResolvedValueOnce(page([task]))
    vi.stubGlobal('URL', { createObjectURL: vi.fn(() => 'blob:intern-proof'), revokeObjectURL: vi.fn() })
    downloadInternTaskProof.mockResolvedValueOnce({ data: new Blob(['%PDF'], { type: 'application/pdf' }) })
    const wrapper = mountView()
    await flushPromises()

    await wrapper.get('[data-testid="view-task-proof-4"]').trigger('click')
    await flushPromises()

    expect(downloadInternTaskProof).toHaveBeenCalledWith(4)
    expect(document.body.querySelector('[role="dialog"]')).not.toBeNull()
    expect(document.body.querySelector('iframe')?.getAttribute('src')).toBe('blob:intern-proof')
    wrapper.unmount()
  })

  it('keeps a pending submission read-only until the company reviews it', async () => {
    listInternTasks.mockResolvedValueOnce(page([{ ...task, submission_status: 'pending_review' }]))
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.get('[data-testid="intern-task-4"]').text()).toContain('waiting for review')
    expect(wrapper.find('[data-testid="submit-task-proof-4"]').exists()).toBe(false)
  })

  it('shows recoverable load and private proof download errors', async () => {
    listInternTasks.mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce(page([task]))
    const wrapper = mountView()
    await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).toContain('Unable to reach the API')

    await wrapper.get('[data-testid="retry-intern-tasks"]').trigger('click')
    await flushPromises()
    downloadInternTaskProof.mockRejectedValueOnce(new Error('not found'))
    await wrapper.get('[data-testid="download-task-proof-4"]').trigger('click')
    await flushPromises()
    expect(downloadInternTaskProof).toHaveBeenCalledWith(4)
    expect(document.body.querySelector('[data-testid="action-alert"]')?.textContent).toContain('Unable to reach the API')
  })
})
