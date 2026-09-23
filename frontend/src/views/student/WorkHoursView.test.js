import { flushPromises, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import WorkHoursView from './WorkHoursView.vue'
import * as ojt from '../../services/ojt'
import { todayInPhilippines } from '../../utils/dateBounds'

vi.mock('../../services/ojt', () => ({
  getStudentInternship: vi.fn(),
  listStudentWorkLogs: vi.fn(),
  createWorkLog: vi.fn(),
  updateWorkLog: vi.fn(),
  updateStudentInternship: vi.fn(),
  deleteWorkLog: vi.fn(),
}))

const page = (items = [], overrides = {}) => ({
  items,
  links: {},
  meta: { current_page: 1, last_page: 1, per_page: 10, total: items.length, from: items.length ? 1 : null, to: items.length || null, ...overrides },
})

const logs = [
  { id: 1, work_date: '2026-09-04', time_in: '08:00', time_out: '17:00', break_minutes: 60, rendered_minutes: 480, status: 'completed', accomplishment_summary: 'Built the tracker.' },
  { id: 2, work_date: '2026-09-03', time_in: '08:00', time_out: '17:00', break_minutes: 60, rendered_minutes: 480, status: 'completed', accomplishment_summary: 'Reviewed the tracker.' },
]

describe('WorkHoursView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    ojt.getStudentInternship.mockResolvedValue({
      start_date: '2026-09-01',
      end_date: '2099-12-31',
      work_days: [1, 2, 3, 4, 5],
      expected_hours_per_day: 8,
      progress: {
        completed_hours: 340,
        required_hours: 500,
        remaining_hours: 160,
        percentage: 68,
      },
    })
    ojt.listStudentWorkLogs.mockResolvedValue(page(logs))
    ojt.createWorkLog.mockResolvedValue({})
    ojt.updateWorkLog.mockResolvedValue({})
    ojt.updateStudentInternship.mockResolvedValue({
      id: 12,
      progress: { completed_hours: 340, required_hours: 600, remaining_hours: 260, percentage: 57 },
    })
    ojt.deleteWorkLog.mockResolvedValue({})
  })

  it('renders completed progress and only simplified work-log actions', async () => {
    const wrapper = mount(WorkHoursView)
    await flushPromises()

    expect(wrapper.text()).toContain('340 / 500 hours')
    expect(wrapper.text()).toContain('Sep 4, 2026')
    expect(wrapper.text()).toContain('8:00 AM')
    expect(wrapper.text()).toContain('8h')
    expect(wrapper.text()).toContain('Sep 1, 2026')
    expect(wrapper.text()).toContain('Dec 31, 2099')
    expect(wrapper.get('[data-testid="add-work-log"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="edit-log-1"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="delete-log-1"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="work-log-1"] [role="status"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Mark as complete')
    expect(wrapper.text()).not.toContain('Save draft')
    expect(wrapper.text()).not.toContain('Approved')
    expect(wrapper.text()).not.toContain('Submit')
    expect(wrapper.text()).not.toContain('Coordinator')
  })

  it('opens the add modal and closes it without leaving a permanent form', async () => {
    const wrapper = mount(WorkHoursView)
    await flushPromises()

    expect(wrapper.find('#work-date').exists()).toBe(false)
    await wrapper.get('[data-testid="add-work-log"]').trigger('click')
    expect(document.body.querySelector('[role="dialog"]')).not.toBeNull()
    expect(document.body.querySelector('#work-date')).not.toBeNull()
    expect(document.body.querySelector('#work-date').value).toContain('Sep')

    document.body.querySelector('#work-date').click()
    await flushPromises()
    const calendar = document.body.querySelector('[data-testid="work-date-calendar"]')
    const offDutyDate = previousWeekday(todayInPhilippines(), 7)
    const workDate = previousWeekday(todayInPhilippines(), 5)
    expect(calendar).not.toBeNull()
    expect(calendar.querySelector(`[data-testid="calendar-day-${offDutyDate}"]`).disabled).toBe(true)
    expect(calendar.querySelector(`[data-testid="calendar-day-${workDate}"]`).disabled).toBe(false)

    document.body.querySelector('[data-testid="cancel-work-log"]').click()
    await flushPromises()
    expect(document.body.querySelector('[role="dialog"]')).toBeNull()
  })

  it('keeps the modal and entered values after validation failure', async () => {
    ojt.createWorkLog.mockRejectedValue({
      response: { status: 422, data: { errors: { time_out: ['Time out is required.'] } } },
    })
    const wrapper = mount(WorkHoursView)
    await flushPromises()
    await wrapper.get('[data-testid="add-work-log"]').trigger('click')

    setInput('#time-in', '08:00')
    setInput('#time-out', '17:00')
    setInput('#break-minutes', '60')
    document.body.querySelector('[role="dialog"] button[type="submit"]').click()
    await flushPromises()

    expect(document.body.querySelector('#work-date').value).toContain('Sep')
    expect(document.body.textContent).toContain('Time out is required.')
    expect(document.body.querySelector('[role="dialog"]')).not.toBeNull()
  })

  it('edits and confirms deletion without a completion action', async () => {
    const wrapper = mount(WorkHoursView)
    await flushPromises()

    await wrapper.get('[data-testid="edit-log-1"]').trigger('click')
    const accomplishment = [...document.body.querySelectorAll('#accomplishment')].at(-1)
    accomplishment.value = 'Updated work.'
    accomplishment.dispatchEvent(new Event('input', { bubbles: true }))
    await nextTick()
    const workLogForm = [...document.body.querySelectorAll('[data-testid="work-log-form"]')].at(-1)
    workLogForm.requestSubmit()
    await flushPromises()
    expect(ojt.updateWorkLog).toHaveBeenCalledWith(1, expect.objectContaining({ accomplishment_summary: 'Updated work.' }))

    await wrapper.get('[data-testid="delete-log-1"]').trigger('click')
    expect(document.body.textContent).toContain('Delete this work log?')
    document.body.querySelector('[data-action="confirm"]').click()
    await flushPromises()
    expect(ojt.deleteWorkLog).toHaveBeenCalledWith(1)
  })

  it('requests only the selected page and no status filter', async () => {
    ojt.listStudentWorkLogs
      .mockResolvedValueOnce(page(logs, { last_page: 2, total: 11, to: 10 }))
      .mockResolvedValue(page([], { current_page: 2, last_page: 2, total: 11, from: 11, to: 11 }))
    const wrapper = mount(WorkHoursView)
    await flushPromises()

    await wrapper.get('[data-page="2"]').trigger('click')
    await flushPromises()
    expect(ojt.listStudentWorkLogs).toHaveBeenLastCalledWith({ page: 2 })
    expect(wrapper.find('[data-status-filter="draft"]').exists()).toBe(false)
  })

  it('moves back to the last valid page after deleting its final record', async () => {
    ojt.listStudentWorkLogs
      .mockResolvedValueOnce(page(logs, { last_page: 3, total: 21, to: 10 }))
      .mockResolvedValueOnce(page([logs[0]], { current_page: 3, last_page: 3, total: 21, from: 21, to: 21 }))
      .mockResolvedValueOnce(page([], { current_page: 3, last_page: 2, total: 20, from: null, to: null }))
      .mockResolvedValueOnce(page(logs, { current_page: 2, last_page: 2, total: 20, from: 11, to: 20 }))
    const wrapper = mount(WorkHoursView)
    await flushPromises()

    await wrapper.get('[data-page="3"]').trigger('click')
    await flushPromises()
    await wrapper.get('[data-testid="delete-log-1"]').trigger('click')
    document.body.querySelector('[data-action="confirm"]').click()
    await flushPromises()

    expect(ojt.deleteWorkLog).toHaveBeenCalledWith(1)
    expect(ojt.listStudentWorkLogs).toHaveBeenLastCalledWith({ page: 2 })
  })

  it('blocks work logging before a future OJT start date', async () => {
    ojt.getStudentInternship.mockResolvedValueOnce({
      start_date: '2099-01-01',
      end_date: '2099-12-31',
      progress: { completed_hours: 0, required_hours: 500, remaining_hours: 500, percentage: 0 },
    })
    ojt.listStudentWorkLogs.mockResolvedValueOnce(page([]))
    const wrapper = mount(WorkHoursView)
    await flushPromises()

    expect(wrapper.get('[data-testid="not-started-state"]').text()).toContain('Work logging will be available on')
    expect(wrapper.get('[data-testid="add-work-log"]').attributes('disabled')).toBeDefined()
    await wrapper.get('[data-testid="add-work-log"]').trigger('click')
    expect(ojt.createWorkLog).not.toHaveBeenCalled()
  })

  it('updates the required work-hour goal from the page', async () => {
    const wrapper = mount(WorkHoursView)
    await flushPromises()

    await wrapper.get('[data-testid="edit-hours-goal"]').trigger('click')
    setInput('#goal-hours', '600')
    document.body.querySelector('#internship-form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
    await flushPromises()

    expect(ojt.updateStudentInternship).toHaveBeenCalledWith(600)
    expect(document.body.textContent).toContain('600 hours')
    expect(document.body.querySelector('[data-testid="action-alert"]')).not.toBeNull()
  })
})

function previousWeekday(value, targetDay) {
  const date = new Date(`${value}T00:00:00`)
  const currentDay = date.getDay() || 7
  let daysBack = (currentDay - targetDay + 7) % 7
  if (daysBack === 0) daysBack = 7
  date.setDate(date.getDate() - daysBack)
  return [
    date.getFullYear(),
    String(date.getMonth() + 1).padStart(2, '0'),
    String(date.getDate()).padStart(2, '0'),
  ].join('-')
}

function setInput(selector, value) {
  const input = document.body.querySelector(selector)
  input.value = value
  input.dispatchEvent(new Event('input', { bubbles: true }))
}
