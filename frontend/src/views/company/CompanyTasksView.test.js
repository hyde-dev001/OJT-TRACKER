import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import CompanyTasksView from './CompanyTasksView.vue'
import {
  approveCompanyTask,
  createCompanyInternTask,
  downloadCompanyTaskProof,
  listCompanyInterns,
  listCompanyInternTasks,
  requestCompanyTaskChanges,
} from '../../services/ojt'

vi.mock('../../services/ojt', () => ({
  approveCompanyTask: vi.fn(),
  createCompanyInternTask: vi.fn(),
  downloadCompanyTaskProof: vi.fn(),
  listCompanyInterns: vi.fn(),
  listCompanyInternTasks: vi.fn(),
  requestCompanyTaskChanges: vi.fn(),
}))

vi.mock('../../services/api', () => ({
  apiErrorMessage: vi.fn(() => 'Unable to reach the API.'),
}))

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

const mountView = () => mount(CompanyTasksView, {
  global: {
    stubs: {
      DatePickerField: DatePickerFieldStub,
      PaginationControls: true,
      PageHeader: { props: ['title', 'description'], template: '<header><h1>{{ title }}</h1><p>{{ description }}</p></header>' },
      StatusBadge: { props: ['status'], template: '<span>{{ status }}</span>' },
    },
  },
})

describe('CompanyTasksView', () => {
  beforeEach(() => vi.resetAllMocks())

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

  it('validates and creates a task for the selected intern', async () => {
    listCompanyInterns.mockResolvedValueOnce([intern])
    listCompanyInternTasks.mockResolvedValue(page())
    createCompanyInternTask.mockResolvedValueOnce({ id: 9 })
    const wrapper = mountView()
    await flushPromises()

    await wrapper.get('[data-testid="company-task-form"]').trigger('submit')
    expect(createCompanyInternTask).not.toHaveBeenCalled()
    expect(wrapper.get('[data-testid="company-task-title-error"]').text()).toContain('Enter a task title')

    await wrapper.get('#company-task-title').setValue('Prepare report')
    await wrapper.get('#company-task-description').setValue('Summarize assigned work.')
    await wrapper.get('#company-task-due-date').setValue('2026-10-20')
    await wrapper.get('[data-testid="company-task-form"]').trigger('submit')
    await flushPromises()

    expect(createCompanyInternTask).toHaveBeenCalledWith(7, {
      title: 'Prepare report',
      description: 'Summarize assigned work.',
      due_date: '2026-10-20',
    })
    expect(wrapper.text()).toContain('Task assigned')
  })

  it('lets a company account holder request changes with a comment and approve without one', async () => {
    listCompanyInterns.mockResolvedValueOnce([intern])
    listCompanyInternTasks.mockResolvedValue(page([task]))
    requestCompanyTaskChanges.mockResolvedValueOnce({ ...task, submission_status: 'changes_requested' })
    approveCompanyTask.mockResolvedValueOnce({ ...task, submission_status: 'approved', status: 'completed' })
    const wrapper = mountView()
    await flushPromises()

    await wrapper.get('[data-testid="request-changes-task-3"]').trigger('click')
    await flushPromises()
    expect(requestCompanyTaskChanges).not.toHaveBeenCalled()
    expect(wrapper.get('[data-testid="task-review-comment-error-3"]').text()).toContain('Add a comment')

    await wrapper.get('#task-review-comment-3').setValue('Please add the date range.')
    await wrapper.get('[data-testid="request-changes-task-3"]').trigger('click')
    await flushPromises()
    expect(requestCompanyTaskChanges).toHaveBeenCalledWith(7, 3, { comment: 'Please add the date range.' })

    await wrapper.get('[data-testid="approve-task-3"]').trigger('click')
    await flushPromises()
    expect(approveCompanyTask).toHaveBeenCalledWith(7, 3, undefined)
    expect(wrapper.text()).toContain('Task approved')
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
    expect(wrapper.get('[role="alert"]').text()).toContain('Unable to reach the API')
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
