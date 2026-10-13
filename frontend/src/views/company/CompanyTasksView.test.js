import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import CompanyTasksView from './CompanyTasksView.vue'
import {
  approveCompanyTask,
  createCompanyInternTask,
  downloadCompanyTaskProof,
  getCompanyInternTask,
  listCompanyInterns,
  listCompanyInternTasks,
  requestCompanyTaskChanges,
} from '../../services/ojt'

const route = vi.hoisted(() => ({ query: {}, hash: '' }))
const routerReplace = vi.hoisted(() => vi.fn())

vi.mock('vue-router', () => ({
  useRoute: () => route,
  useRouter: () => ({ replace: routerReplace }),
}))

vi.mock('../../services/ojt', () => ({
  approveCompanyTask: vi.fn(),
  createCompanyInternTask: vi.fn(),
  downloadCompanyTaskProof: vi.fn(),
  getCompanyInternTask: vi.fn(),
  listCompanyInterns: vi.fn(),
  listCompanyInternTasks: vi.fn(),
  requestCompanyTaskChanges: vi.fn(),
}))

vi.mock('../../services/api', () => ({
  apiErrorMessage: vi.fn(() => 'Unable to reach the API.'),
}))

enableAutoUnmount(afterEach)

const intern = { id: 7, name: 'Taylor Lee', email: 'taylor.lee@example.com' }
const task = {
  id: 3,
  title: 'Prepare report',
  description: 'Summarize assigned work.',
  due_date: '2026-10-20',
  status: 'in_progress',
  submission_status: 'pending_review',
  submission_comment: 'Proof is attached.',
  review_comment: null,
  proof_available: true,
}
const page = (items = []) => ({
  items,
  links: {},
  meta: { current_page: 1, last_page: 1, per_page: 10, total: items.length, from: items.length ? 1 : null, to: items.length || null },
})

const DatePickerFieldStub = {
  props: ['id', 'modelValue', 'invalid', 'describedby'],
  emits: ['update:modelValue'],
  template: '<input :id="id" :value="modelValue" :aria-invalid="invalid" @input="$emit(\'update:modelValue\', $event.target.value)">',
}

const setBodyInputValue = async (selector, value) => {
  const input = document.body.querySelector(selector)
  input.value = value
  input.dispatchEvent(new Event('input', { bubbles: true }))
  await flushPromises()
}

const submitBodyForm = async (selector) => {
  document.body.querySelector(selector).dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
  await flushPromises()
}

const mountView = () => mount(CompanyTasksView, {
  global: {
    stubs: {
      DatePickerField: DatePickerFieldStub,
      PaginationControls: { props: ['meta'], template: '<div data-testid="pagination-meta">{{ meta && meta.total }}</div>' },
      PageHeader: { props: ['title', 'description'], template: '<header><h1>{{ title }}</h1><p>{{ description }}</p></header>' },
      StatusBadge: { props: ['status'], template: '<span>{{ status }}</span>' },
    },
  },
})

describe('CompanyTasksView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    route.query = {}
    route.hash = ''
  })
  afterEach(() => vi.unstubAllGlobals())

  it('shows loading and empty states when there are no interns', async () => {
    let resolveInterns
    listCompanyInterns.mockReturnValue(new Promise((resolve) => { resolveInterns = resolve }))
    const wrapper = mountView()

    expect(wrapper.text()).toContain('Loading intern accounts')
    resolveInterns([])
    await flushPromises()

    expect(wrapper.text()).toContain('No intern accounts yet')
    expect(listCompanyInternTasks).not.toHaveBeenCalled()
  })

  it('loads the selected intern tasks and shows pending review without assignment edit or delete actions', async () => {
    listCompanyInterns.mockResolvedValueOnce([intern])
    listCompanyInternTasks.mockResolvedValueOnce(page([task]))
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.get('#company-task-intern-select').element.value).toBe('7')
    expect(listCompanyInternTasks).toHaveBeenCalledWith(7, { page: 1 })
    expect(wrapper.get('[data-testid="company-task-3"]').text()).toContain('Pending review')
    expect(wrapper.get('[data-testid="company-task-3"]').text()).toContain('Proof is attached.')
    expect(wrapper.find('[data-testid="edit-company-task-3"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="delete-company-task-3"]').exists()).toBe(false)
  })

  it('selects the notification intern and shows an exact off-page task with proof controls', async () => {
    const otherIntern = { id: 8, name: 'Alex Morgan', email: 'alex@example.com' }
    const target = { ...task, id: 99, title: 'Exact submitted task', proof_available: true }
    route.query = { intern_id: '8', task_id: '99' }
    listCompanyInterns.mockResolvedValueOnce([intern, otherIntern])
    listCompanyInternTasks.mockResolvedValueOnce(page([task]))
    getCompanyInternTask.mockResolvedValueOnce(target)
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.get('#company-task-intern-select').element.value).toBe('8')
    expect(getCompanyInternTask).toHaveBeenCalledWith(8, 99)
    expect(wrapper.get('[data-testid="company-task-99"]').text()).toContain('Exact submitted task')
    expect(wrapper.get('[data-testid="company-task-99"]').attributes('data-notification-target')).toBe('true')
    expect(wrapper.get('[data-testid="view-task-proof-99"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="pagination-meta"]').text()).toBe('1')
    expect(routerReplace).toHaveBeenCalledWith({ query: route.query, hash: '#company-task-99' })
    wrapper.unmount()
  })

  it('shows a recoverable message when the notification task cannot be loaded', async () => {
    route.query = { intern_id: '7', task_id: '404' }
    listCompanyInterns.mockResolvedValueOnce([intern])
    listCompanyInternTasks.mockResolvedValueOnce(page())
    getCompanyInternTask.mockRejectedValueOnce(new Error('missing'))
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.get('[data-testid="company-task-target-error"]').text()).toContain('Unable to reach the API')
    expect(wrapper.get('[data-testid="company-task-target-error"] a').attributes('href')).toBe('/company/tasks')
    wrapper.unmount()
  })

  it('validates and creates a task for the selected intern', async () => {
    listCompanyInterns.mockResolvedValueOnce([intern])
    listCompanyInternTasks.mockResolvedValue(page())
    createCompanyInternTask.mockResolvedValueOnce({ id: 9 })
    const wrapper = mountView()
    await flushPromises()

    expect(document.body.querySelector('[data-testid="company-task-form"]')).toBeNull()
    await wrapper.get('[data-testid="assign-task-open"]').trigger('click')
    expect(document.body.querySelector('[role="dialog"]')?.textContent).toContain('Assign a task')
    await submitBodyForm('[data-testid="company-task-form"]')
    expect(createCompanyInternTask).not.toHaveBeenCalled()
    expect(document.body.querySelector('[data-testid="company-task-title-error"]')?.textContent).toContain('Enter a task title')

    await setBodyInputValue('#company-task-title', 'Prepare report')
    await setBodyInputValue('#company-task-description', 'Summarize assigned work.')
    await setBodyInputValue('#company-task-due-date', '2026-10-20')
    await submitBodyForm('[data-testid="company-task-form"]')
    await flushPromises()

    expect(createCompanyInternTask).toHaveBeenCalledWith(7, {
      title: 'Prepare report',
      description: 'Summarize assigned work.',
      due_date: '2026-10-20',
    })
    expect(document.body.textContent).toContain('Task assigned')
    expect(document.body.querySelector('[data-testid="company-task-form"]')).toBeNull()
  })

  it('lets a company account holder request changes with a comment and approve without one', async () => {
    listCompanyInterns.mockResolvedValueOnce([intern])
    listCompanyInternTasks.mockResolvedValue(page([task]))
    requestCompanyTaskChanges.mockResolvedValueOnce({ ...task, submission_status: 'changes_requested' })
    approveCompanyTask.mockResolvedValueOnce({ ...task, submission_status: 'approved', status: 'completed' })
    vi.stubGlobal('URL', { createObjectURL: vi.fn(() => 'blob:company-proof'), revokeObjectURL: vi.fn() })
    downloadCompanyTaskProof.mockResolvedValueOnce({ data: new Blob(['%PDF'], { type: 'application/pdf' }) })
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.get('[data-testid="approve-task-3"]').element.disabled).toBe(true)
    expect(wrapper.text()).toContain('View the proof before approving.')

    await wrapper.get('[data-testid="request-changes-task-3"]').trigger('click')
    await flushPromises()
    expect(requestCompanyTaskChanges).not.toHaveBeenCalled()
    expect(wrapper.get('[data-testid="task-review-comment-error-3"]').text()).toContain('Add a comment')

    await wrapper.get('#task-review-comment-3').setValue('Please add the date range.')
    await wrapper.get('[data-testid="request-changes-task-3"]').trigger('click')
    await flushPromises()
    expect(requestCompanyTaskChanges).toHaveBeenCalledWith(7, 3, { comment: 'Please add the date range.' })
    expect(document.body.textContent).toContain('Changes requested')
    document.body.querySelector('[data-testid="alert-close"]')?.click()

    await wrapper.get('[data-testid="view-task-proof-3"]').trigger('click')
    await flushPromises()
    expect(document.body.querySelector('[role="dialog"]')).not.toBeNull()
    expect(wrapper.get('[data-testid="approve-task-3"]').element.disabled).toBe(false)

    await wrapper.get('[data-testid="approve-task-3"]').trigger('click')
    await flushPromises()
    expect(approveCompanyTask).toHaveBeenCalledWith(7, 3, undefined)
    expect(document.body.textContent).toContain('Task approved')
  })

  it('shows a recoverable error when a proof download fails', async () => {
    listCompanyInterns.mockResolvedValueOnce([intern])
    listCompanyInternTasks.mockResolvedValueOnce(page([task]))
    downloadCompanyTaskProof.mockRejectedValueOnce(new Error('offline'))
    const wrapper = mountView()
    await flushPromises()

    await wrapper.get('[data-testid="download-task-proof-3"]').trigger('click')
    await flushPromises()

    expect(downloadCompanyTaskProof).toHaveBeenCalledWith(7, 3)
    expect(document.body.querySelector('[data-testid="action-alert"]')?.textContent).toContain('Unable to reach the API')
  })

  it('keeps approval disabled when the proof cannot be opened', async () => {
    listCompanyInterns.mockResolvedValueOnce([intern])
    listCompanyInternTasks.mockResolvedValueOnce(page([task]))
    downloadCompanyTaskProof.mockRejectedValueOnce(new Error('offline'))
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.get('[data-testid="approve-task-3"]').element.disabled).toBe(true)
    expect(wrapper.text()).toContain('View the proof before approving.')
    await wrapper.get('[data-testid="view-task-proof-3"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-testid="approve-task-3"]').element.disabled).toBe(true)
  })

  it('lets the company account holder preview private proof before reviewing it', async () => {
    listCompanyInterns.mockResolvedValueOnce([intern])
    listCompanyInternTasks.mockResolvedValueOnce(page([task]))
    vi.stubGlobal('URL', { createObjectURL: vi.fn(() => 'blob:company-proof'), revokeObjectURL: vi.fn() })
    downloadCompanyTaskProof.mockResolvedValueOnce({ data: new Blob(['%PDF'], { type: 'application/pdf' }) })
    const wrapper = mountView()
    await flushPromises()

    await wrapper.get('[data-testid="view-task-proof-3"]').trigger('click')
    await flushPromises()

    expect(downloadCompanyTaskProof).toHaveBeenCalledWith(7, 3)
    expect(document.body.querySelector('[role="dialog"]')).not.toBeNull()
    expect(document.body.querySelector('iframe')?.getAttribute('src')).toBe('blob:company-proof')
    expect(wrapper.get('[data-testid="approve-task-3"]').element.disabled).toBe(false)
    wrapper.unmount()
  })

  it('shows a retry action when tasks cannot load', async () => {
    listCompanyInterns.mockResolvedValueOnce([intern])
    listCompanyInternTasks.mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce(page())
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Unable to reach the API')
    await wrapper.get('[data-testid="retry-company-tasks"]').trigger('click')
    await flushPromises()

    expect(listCompanyInternTasks).toHaveBeenCalledTimes(2)
    expect(wrapper.text()).toContain('No tasks assigned yet')
  })
})
