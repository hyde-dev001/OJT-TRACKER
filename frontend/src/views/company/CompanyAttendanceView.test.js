import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { todayInPhilippines } from '../../utils/dateBounds'
import CompanyAttendanceView from './CompanyAttendanceView.vue'
import {
  listCompanyInterns,
  listCompanyInternWorkLogs,
  startCompanyWorkLog,
  updateCompanyWorkLog,
} from '../../services/ojt'

enableAutoUnmount(afterEach)

vi.mock('../../services/ojt', () => ({
  listCompanyInterns: vi.fn(),
  listCompanyInternWorkLogs: vi.fn(),
  startCompanyWorkLog: vi.fn(),
  updateCompanyWorkLog: vi.fn(),
}))

const DatePickerFieldStub = {
  props: ['id', 'modelValue', 'invalid', 'allowedWeekdays', 'allowCurrentValue'],
  emits: ['update:modelValue'],
  template: '<input :id="id" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)">',
}

const AppModalStub = {
  props: ['open'],
  template: '<div v-if="open"><slot /><slot name="footer" /></div>',
}

const intern = {
  id: 7,
  name: 'Taylor Lee',
  email: 'taylor.lee@example.com',
  start_date: '2026-10-01',
  end_date: '2026-10-31',
  work_days: [1, 2, 3, 4, 5],
}

const page = (items = []) => ({
  items,
  links: {},
  meta: { current_page: 1, last_page: 1, per_page: 10, total: items.length, from: items.length ? 1 : null, to: items.length || null },
})

const mountView = () => mount(CompanyAttendanceView, {
  global: {
    stubs: {
      AppModal: AppModalStub,
      DatePickerField: DatePickerFieldStub,
      PaginationControls: true,
      PageHeader: { props: ['title', 'description'], template: '<header><h1>{{ title }}</h1><p>{{ description }}</p></header>' },
    },
  },
})

describe('CompanyAttendanceView', () => {
  beforeEach(() => vi.resetAllMocks())

  it('shows a loading state followed by an empty state when there are no interns', async () => {
    let resolveInterns
    listCompanyInterns.mockReturnValue(new Promise((resolve) => { resolveInterns = resolve }))
    const wrapper = mountView()

    expect(wrapper.text()).toContain('Loading intern accounts')

    resolveInterns([])
    await flushPromises()

    expect(wrapper.text()).toContain('No intern accounts yet')
    expect(listCompanyInternWorkLogs).not.toHaveBeenCalled()
  })

  it('shows a recoverable error when intern accounts cannot load', async () => {
    listCompanyInterns.mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce([intern])
    listCompanyInternWorkLogs.mockResolvedValueOnce(page())
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('The API is unavailable')

    await wrapper.get('[data-testid="retry-company-attendance"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-testid="attendance-intern-select"]').element.value).toBe('7')
    expect(listCompanyInternWorkLogs).toHaveBeenCalledWith(7, { page: 1 })
  })

  it('shows one recoverable error when an intern’s work sessions cannot load', async () => {
    listCompanyInterns.mockResolvedValueOnce([intern])
    listCompanyInternWorkLogs.mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce(page())
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.findAll('[role="alert"]')).toHaveLength(1)
    expect(wrapper.get('[role="alert"]').text()).toContain('The API is unavailable')

    await wrapper.get('[data-testid="retry-company-work-logs"]').trigger('click')
    await flushPromises()

    expect(listCompanyInternWorkLogs).toHaveBeenCalledTimes(2)
    expect(wrapper.text()).toContain('No work sessions yet')
  })

  it('lets the account holder select an intern and loads that intern’s sessions', async () => {
    const secondIntern = { ...intern, id: 8, name: 'Jordan Park' }
    listCompanyInterns.mockResolvedValueOnce([intern, secondIntern])
    listCompanyInternWorkLogs.mockResolvedValue(page())
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.get('[data-testid="attendance-intern-select"]').element.value).toBe('7')
    await wrapper.get('[data-testid="attendance-intern-select"]').setValue('8')
    await flushPromises()

    expect(listCompanyInternWorkLogs).toHaveBeenLastCalledWith(8, { page: 1 })
  })

  it('records time in and reloads the selected intern’s sessions', async () => {
    const openLog = {
      id: 15,
      work_date: todayInPhilippines(),
      time_in: '08:00',
      time_out: null,
      break_minutes: 0,
      rendered_minutes: null,
      status: 'in_progress',
    }
    listCompanyInterns.mockResolvedValueOnce([intern])
    listCompanyInternWorkLogs.mockResolvedValueOnce(page()).mockResolvedValueOnce(page([openLog]))
    startCompanyWorkLog.mockResolvedValueOnce(openLog)
    const wrapper = mountView()
    await flushPromises()

    await wrapper.get('[data-testid="time-in-7"]').trigger('click')
    await flushPromises()

    expect(startCompanyWorkLog).toHaveBeenCalledWith(7)
    expect(listCompanyInternWorkLogs).toHaveBeenCalledTimes(2)
    expect(document.body.textContent).toContain('Time in recorded')
    expect(wrapper.text()).toContain('In progress')
  })

  it('closes an open session through Edit work hours without a quick time-out button', async () => {
    const openLog = {
      id: 15,
      work_date: todayInPhilippines(),
      time_in: '08:00',
      time_out: null,
      break_minutes: 0,
      rendered_minutes: null,
      status: 'in_progress',
    }
    const completedLog = { ...openLog, time_out: '17:00', break_minutes: 30, rendered_minutes: 510, status: 'completed' }
    listCompanyInterns.mockResolvedValueOnce([intern])
    listCompanyInternWorkLogs.mockResolvedValueOnce(page([openLog])).mockResolvedValueOnce(page([completedLog]))
    updateCompanyWorkLog.mockResolvedValueOnce(completedLog)
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.find('#attendance-break-minutes').exists()).toBe(false)
    expect(wrapper.find('[data-testid="time-out-7"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Use Edit work hours below to enter the time out and any break minutes.')
    await wrapper.get('[data-testid="edit-work-log-15"]').trigger('click')
    await wrapper.get('#edit-work-log-time-out').setValue('17:00')
    await wrapper.get('#edit-work-log-break-minutes').setValue('30')
    await wrapper.get('[data-testid="edit-work-log-form"]').trigger('submit')
    await flushPromises()

    expect(updateCompanyWorkLog).toHaveBeenCalledWith(7, 15, {
      work_date: todayInPhilippines(),
      time_in: '08:00',
      time_out: '17:00',
      break_minutes: 30,
    })
    expect(listCompanyInternWorkLogs).toHaveBeenCalledTimes(2)
    expect(wrapper.text()).toContain('Completed')
    expect(document.body.textContent).toContain('Work hours updated')
  })

  it('edits completed work hours without sending derived values and shows success', async () => {
    const completedLog = {
      id: 15,
      work_date: '2026-10-12',
      time_in: '08:00',
      time_out: '17:00',
      break_minutes: 60,
      rendered_minutes: 480,
      status: 'completed',
    }
    listCompanyInterns.mockResolvedValueOnce([intern])
    listCompanyInternWorkLogs.mockResolvedValue(page([completedLog]))
    updateCompanyWorkLog.mockResolvedValueOnce({ ...completedLog, rendered_minutes: 495 })
    const wrapper = mountView()
    await flushPromises()

    await wrapper.get('[data-testid="edit-work-log-15"]').trigger('click')
    await wrapper.get('#edit-work-log-date').setValue('2026-10-13')
    await wrapper.get('#edit-work-log-time-in').setValue('08:15')
    await wrapper.get('#edit-work-log-time-out').setValue('17:00')
    await wrapper.get('#edit-work-log-break-minutes').setValue('30')
    await wrapper.get('[data-testid="edit-work-log-form"]').trigger('submit')
    await flushPromises()

    expect(updateCompanyWorkLog).toHaveBeenCalledWith(7, 15, {
      work_date: '2026-10-13',
      time_in: '08:15',
      time_out: '17:00',
      break_minutes: 30,
    })
    expect(updateCompanyWorkLog.mock.calls[0][2]).not.toHaveProperty('rendered_minutes')
    expect(updateCompanyWorkLog.mock.calls[0][2]).not.toHaveProperty('status')
    expect(document.body.textContent).toContain('Work hours updated')
  })
})
