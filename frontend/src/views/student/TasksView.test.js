import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import TasksView from './TasksView.vue'
import * as ojt from '../../services/ojt'

vi.mock('../../services/ojt', () => ({
  getStudentInternship: vi.fn(),
  listStudentTasks: vi.fn(),
  createTask: vi.fn(),
  updateTask: vi.fn(),
  deleteTask: vi.fn(),
  startTask: vi.fn(),
  completeTask: vi.fn(),
}))

const page = (items = [], overrides = {}) => ({
  items,
  links: {},
  meta: { current_page: 1, last_page: 1, per_page: 10, total: items.length, from: items.length ? 1 : null, to: items.length || null, ...overrides },
})

describe('student TasksView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    ojt.getStudentInternship.mockResolvedValue({ id: 12, start_date: '2026-09-01', end_date: '2099-12-31' })
    ojt.listStudentTasks.mockResolvedValue(page([
      { id: 1, title: 'To do task', status: 'to_do', due_date: '2026-09-20' },
      { id: 2, title: 'Progress task', status: 'in_progress', due_date: '2026-09-21' },
      { id: 3, title: 'Completed task', status: 'completed', due_date: '2026-09-22' },
    ]))
    ojt.createTask.mockResolvedValue({})
    ojt.updateTask.mockResolvedValue({})
    ojt.deleteTask.mockResolvedValue({})
    ojt.startTask.mockResolvedValue({})
    ojt.completeTask.mockResolvedValue({})
  })

  it('filters tasks and exposes only the student lifecycle', async () => {
    const wrapper = mount(TasksView)
    await flushPromises()

    expect(wrapper.findAll('[data-testid^="task-"]')).toHaveLength(3)
    await wrapper.get('[data-testid="filter-completed"]').trigger('click')
    expect(wrapper.find('[data-testid="task-3"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="task-1"]').exists()).toBe(false)

    await wrapper.get('[data-testid="filter-all"]').trigger('click')
    expect(wrapper.get('[data-testid="start-1"]').text()).toContain('Start')
    expect(wrapper.get('[data-testid="complete-2"]').text()).toContain('Mark completed')
    expect(wrapper.get('[data-testid="edit-1"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Sep 20, 2026')
    expect(wrapper.text()).not.toContain('Submit')
    expect(wrapper.text()).not.toContain('Needs Revision')
  })

  it('requests the selected task filter and page from the server', async () => {
    ojt.listStudentTasks
      .mockResolvedValueOnce(page([
        { id: 1, title: 'First task', status: 'to_do' },
      ], { last_page: 2, total: 11, to: 10 }))
      .mockResolvedValueOnce(page([
        { id: 3, title: 'Completed task', status: 'completed' },
      ], { last_page: 2, total: 11, to: 10 }))
      .mockResolvedValueOnce(page([
        { id: 4, title: 'Second page task', status: 'completed' },
      ], { current_page: 2, last_page: 2, total: 11, from: 11, to: 11 }))
    const wrapper = mount(TasksView)
    await flushPromises()

    expect(ojt.listStudentTasks).toHaveBeenCalledWith({ page: 1, filter: 'all' })
    await wrapper.get('[data-testid="filter-completed"]').trigger('click')
    await flushPromises()
    expect(ojt.listStudentTasks).toHaveBeenLastCalledWith({ page: 1, filter: 'completed' })

    await wrapper.get('[data-page="2"]').trigger('click')
    await flushPromises()
    expect(ojt.listStudentTasks).toHaveBeenLastCalledWith({ page: 2, filter: 'completed' })
  })

  it('shows a no-match state for a selected task filter', async () => {
    ojt.listStudentTasks
      .mockResolvedValueOnce(page([{ id: 1, title: 'To do task', status: 'to_do' }]))
      .mockResolvedValueOnce(page([]))
    const wrapper = mount(TasksView)
    await flushPromises()
    await wrapper.get('[data-testid="filter-completed"]').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('No tasks match this filter')
  })

  it('creates a task from a modal and supports start, complete, and delete', async () => {
    const wrapper = mount(TasksView)
    await flushPromises()

    await wrapper.get('[data-testid="add-task"]').trigger('click')
    expect(document.body.querySelector('[role="dialog"]')).not.toBeNull()
    await document.body.querySelector('#task-title').dispatchEvent(new Event('input', { bubbles: true }))
    document.body.querySelector('#task-title').value = 'New task'
    document.body.querySelector('#task-title').dispatchEvent(new Event('input', { bubbles: true }))
    document.body.querySelector('[data-testid="task-form"]').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
    await flushPromises()
    expect(ojt.createTask).toHaveBeenCalledWith(expect.objectContaining({ internship_id: 12, title: 'New task' }))
    expect(document.body.querySelector('[data-testid="action-alert"]')).not.toBeNull()
    document.body.querySelector('[data-action="alert-close"]').click()

    await wrapper.get('[data-testid="start-1"]').trigger('click')
    await flushPromises()
    expect(ojt.startTask).toHaveBeenCalledWith(1)

    await wrapper.get('[data-testid="complete-2"]').trigger('click')
    await flushPromises()
    expect(ojt.completeTask).toHaveBeenCalledWith(2)

    await wrapper.get('[data-testid="delete-1"]').trigger('click')
    document.body.querySelector('[data-action="confirm"]').click()
    await flushPromises()
    expect(ojt.deleteTask).toHaveBeenCalledWith(1)
  })

  it('limits task due dates to the internship period and today', async () => {
    const wrapper = mount(TasksView)
    await flushPromises()

    await wrapper.get('[data-testid="add-task"]').trigger('click')

    const dateField = document.body.querySelector('#task-due-date')
    expect(dateField.getAttribute('type')).toBe('text')
    expect(dateField.getAttribute('data-min')).toBe('2026-09-01')
    expect(dateField.getAttribute('data-max')).toBe('2099-12-31')
    dateField.click()
    await flushPromises()
    const calendar = document.body.querySelector('[data-testid="task-due-date-calendar"]')
    expect(calendar).not.toBeNull()
    expect(calendar.querySelector('[data-testid="calendar-day-2026-08-31"]').disabled).toBe(true)
    expect(calendar.querySelector('[data-testid="calendar-day-2026-09-01"]').disabled).toBe(false)
  })

  it('shows readable empty and error states', async () => {
    ojt.listStudentTasks.mockResolvedValueOnce([])
    const emptyWrapper = mount(TasksView)
    await flushPromises()
    expect(emptyWrapper.text()).toContain('No tasks yet')

    ojt.listStudentTasks.mockRejectedValueOnce(new Error('network'))
    const errorWrapper = mount(TasksView)
    await flushPromises()
    expect(errorWrapper.get('[role="alert"]').text()).toContain('The API is unavailable.')
  })
})
