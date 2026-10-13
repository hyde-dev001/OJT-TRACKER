import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import InternWorkHoursView from './InternWorkHoursView.vue'
import * as ojt from '../../services/ojt'

vi.mock('../../services/ojt', () => ({
  getInternInternship: vi.fn(),
  listInternWorkLogs: vi.fn(),
}))

const internship = {
  start_date: '2026-09-01',
  end_date: '2027-02-28',
  progress: {
    completed_minutes: 20400,
    completed_hours: 340,
    required_minutes: 30000,
    required_hours: 500,
    remaining_minutes: 9600,
    remaining_hours: 160,
    percentage: 68,
  },
}

const page = (items = [], overrides = {}) => ({
  items,
  links: {},
  meta: { current_page: 1, last_page: 1, per_page: 10, total: items.length, from: items.length ? 1 : null, to: items.length || null, ...overrides },
})

describe('InternWorkHoursView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    ojt.getInternInternship.mockResolvedValue(internship)
    ojt.listInternWorkLogs.mockResolvedValue(page([
      { id: 15, work_date: '2026-10-12', time_in: '08:00', time_out: '17:00', break_minutes: 60, rendered_minutes: 480, status: 'completed' },
    ]))
  })

  it('shows the backend progress and completed work sessions read-only', async () => {
    const wrapper = mount(InternWorkHoursView)
    await flushPromises()

    expect(wrapper.text()).toContain('340 hours completed')
    expect(wrapper.text()).toContain('500 hours required')
    expect(wrapper.text()).toContain('160 hours remaining')
    expect(wrapper.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('68')
    expect(wrapper.text()).toContain('Oct 12, 2026')
    expect(wrapper.text()).toContain('8:00 AM')
    expect(wrapper.text()).toContain('8h')
    expect(wrapper.find('form').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Edit work hours')
    expect(wrapper.text()).not.toContain('Time in')
    expect(wrapper.text()).not.toContain('Time out')
  })

  it('opens a work-session breakdown with its time and break details', async () => {
    const wrapper = mount(InternWorkHoursView)
    await flushPromises()

    await wrapper.get('[data-testid="view-work-log-15"]').trigger('click')

    const dialog = document.body.querySelector('[role="dialog"]')
    expect(dialog?.textContent).toContain('Work session details')
    expect(dialog?.textContent).toContain('Oct 12, 2026')
    expect(dialog?.textContent).toContain('8:00 AM')
    expect(dialog?.textContent).toContain('5:00 PM')
    expect(dialog?.textContent).toContain('60 minutes')
    expect(dialog?.textContent).toContain('8h')
    document.body.querySelector('[data-testid="close-work-session-details"]').click()
    await flushPromises()
    expect(document.body.querySelector('[role="dialog"]')).toBeNull()
    wrapper.unmount()
  })

  it('labels an open session in progress and does not show its duration', async () => {
    ojt.listInternWorkLogs.mockResolvedValue(page([
      { id: 16, work_date: '2026-10-13', time_in: '09:15', time_out: null, break_minutes: null, rendered_minutes: null, status: 'open' },
    ]))
    const wrapper = mount(InternWorkHoursView)
    await flushPromises()

    const row = wrapper.get('[data-testid="intern-work-log-16"]')
    expect(row.text()).toContain('In progress')
    expect(row.text()).not.toContain('0m')
    expect(row.text()).not.toContain('NaN')

    await wrapper.get('[data-testid="view-work-log-16"]').trigger('click')
    const dialog = document.body.querySelector('[role="dialog"]')
    expect(dialog?.textContent).toContain('Time outIn progress')
    expect(dialog?.textContent).toContain('Not available while in progress')
  })

  it('shows completed hours without a percentage when the target is not set', async () => {
    ojt.getInternInternship.mockResolvedValue({
      ...internship,
      progress: { ...internship.progress, required_minutes: null, required_hours: null, remaining_minutes: null, remaining_hours: null, percentage: null },
    })
    const wrapper = mount(InternWorkHoursView)
    await flushPromises()

    expect(wrapper.text()).toContain('340 hours completed')
    expect(wrapper.text()).toContain('Progress percentage is unavailable until required hours are set')
    expect(wrapper.find('[role="progressbar"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('NaN%')
  })

  it('loads the selected page of work sessions', async () => {
    ojt.listInternWorkLogs
      .mockResolvedValueOnce(page([{ id: 15, work_date: '2026-10-12', time_in: '08:00', time_out: '17:00', rendered_minutes: 480, status: 'completed' }], { last_page: 2, total: 11, to: 10 }))
      .mockResolvedValueOnce(page([{ id: 25, work_date: '2026-10-09', time_in: '08:00', time_out: '17:00', rendered_minutes: 480, status: 'completed' }], { current_page: 2, last_page: 2, total: 11, from: 11, to: 11 }))
    const wrapper = mount(InternWorkHoursView)
    await flushPromises()

    await wrapper.get('[data-page="2"]').trigger('click')
    await flushPromises()

    expect(ojt.listInternWorkLogs).toHaveBeenLastCalledWith({ page: 2 })
    expect(wrapper.find('[data-testid="intern-work-log-15"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="intern-work-log-25"]').exists()).toBe(true)
  })

  it('allows retry after a recoverable load error', async () => {
    ojt.getInternInternship.mockRejectedValueOnce({ response: { status: 500 } })
    const wrapper = mount(InternWorkHoursView)
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('The server could not complete that request.')
    await wrapper.get('[data-testid="retry-work-hours"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(ojt.getInternInternship).toHaveBeenCalledTimes(2)
  })
})
