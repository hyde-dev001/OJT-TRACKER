import { flushPromises, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import OverviewView from './OverviewView.vue'
import * as ojt from '../../services/ojt'

vi.mock('../../services/ojt', () => ({
  getStudentOverview: vi.fn(),
  exportStudentSummary: vi.fn(),
}))

const pdfResponse = (headers = {
  'content-type': 'application/pdf',
  'content-disposition': 'attachment; filename="OJT-Progress-Summary-2026-09-24.pdf"',
}) => ({ data: new Blob(['%PDF']), headers })

const stubDownload = () => {
  const createObjectURL = vi.fn(() => 'blob:ojt-summary')
  const revokeObjectURL = vi.fn()
  const clicks = []
  vi.stubGlobal('URL', { createObjectURL, revokeObjectURL })
  vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function () {
    clicks.push({ href: this.href, download: this.download })
  })

  return { createObjectURL, revokeObjectURL, clicks }
}

const overview = () => ({
  internship: {
    start_date: '2026-09-01',
    end_date: '2026-12-15',
    work_days: [1, 2, 3, 4, 5],
    expected_daily_minutes: 480,
  },
  progress: {
    required_minutes: 30000,
    rendered_minutes: 14400,
    remaining_minutes: 15600,
    percentage: 48,
  },
  pace: {
    status: 'on_track',
    remaining_scheduled_days: 38,
    required_daily_minutes: 411,
    expected_daily_minutes: 480,
  },
  attention: {
    items: [{
      type: 'requirement',
      id: 7,
      title: 'Medical clearance',
      due_date: '2026-09-23',
      reason: 'due_today',
      priority: 5,
      href: '/student/requirements',
    }],
    additional_count: 0,
  },
  completion: {
    ready: false,
    remaining_minutes: 15600,
    incomplete_required_requirements: [{ id: 7, title: 'Medical clearance', due_date: '2026-09-23' }],
    blockers: ['hours_remaining', 'required_requirements_incomplete'],
  },
})

const global = {
  stubs: {
    RouterLink: { props: ['to'], template: '<a :href="to"><slot /></a>' },
  },
}

describe('student OverviewView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    ojt.getStudentOverview.mockResolvedValue(overview())
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('renders backend progress, pace, attention, and readiness data', async () => {
    const wrapper = mount(OverviewView, { global })
    await flushPromises()

    expect(wrapper.get('[data-testid="overview-export"]').text()).toBe('Export Summary')
    expect(wrapper.get('[data-testid="overview-context"]').text()).toContain('Mon-Fri')
    expect(wrapper.get('[data-testid="overview-context"]').text()).toContain('8h expected/day')
    expect(wrapper.get('[data-testid="overview-progress"]').text()).toContain('48%')
    expect(wrapper.get('[data-testid="overview-progress"]').text()).toContain('240h')
    expect(wrapper.get('[data-testid="overview-progress-meta"]').classes()).toContain('flex')
    expect(wrapper.get('[data-testid="overview-progress-meta"]').classes()).toContain('items-baseline')
    expect(wrapper.get('[data-testid="overview-progress"]').text()).not.toContain('OJT period')
    expect(wrapper.get('[data-testid="overview-progress"]').text()).not.toContain('Edit total hours')
    expect(wrapper.get('[data-testid="overview-pace"]').text()).toContain('Current Pace')
    expect(wrapper.get('[data-testid="overview-pace"]').text()).toContain('6h 51m')
    expect(wrapper.get('[data-testid="overview-pace"]').text()).toContain('8h')
    expect(wrapper.get('[data-testid="overview-status"]').text()).toContain('On track')
    expect(wrapper.get('[data-testid="overview-attention-item"]').text()).toContain('Medical clearance')
    expect(wrapper.get('[data-testid="overview-attention-item"] a').attributes('href')).toBe('/student/requirements')
    expect(wrapper.get('[data-testid="overview-attention"]').text()).not.toContain('Priority')
    expect(wrapper.get('[data-testid="overview-completion"]').text()).toContain('260h')
    expect(wrapper.get('[data-testid="overview-completion"]').text()).toContain('OJT hours remaining')
    expect(wrapper.get('[data-testid="overview-completion"]').text()).toContain('Medical clearance')
    expect(wrapper.get('[data-testid="overview-completion"]').text()).not.toContain('or a required item')
    expect(wrapper.text()).not.toContain('Work logs')
  })

  it('keeps long completion blocker lists compact and links to the full list', async () => {
    const data = overview()
    data.completion.incomplete_required_requirements = Array.from({ length: 6 }, (_, index) => ({
      id: index + 1,
      title: `Requirement ${index + 1}`,
      due_date: '2026-09-23',
    }))
    ojt.getStudentOverview.mockResolvedValue(data)

    const wrapper = mount(OverviewView, { global })
    await flushPromises()

    const requirements = wrapper.findAll('[data-testid="overview-completion-requirement"]')
    expect(requirements).toHaveLength(3)
    expect(requirements[0].text()).toContain('Requirement 1')
    expect(requirements[2].text()).toContain('Requirement 3')
    expect(wrapper.get('[data-testid="overview-completion-requirement-count"]').text()).toContain('6 incomplete required items')
    expect(wrapper.get('[data-testid="overview-completion-requirements-more"]').text()).toBe('+3 more')
    expect(wrapper.get('[data-testid="overview-completion-requirements-more"]').attributes('href')).toBe('/student/requirements')
    expect(wrapper.get('[data-testid="overview-completion"]').text()).not.toContain('Requirement 4')
  })

  it('shows the ready state when the backend reports readiness', async () => {
    const data = overview()
    data.progress.rendered_minutes = 30000
    data.progress.remaining_minutes = 0
    data.progress.percentage = 100
    data.pace.status = 'complete'
    data.completion = {
      ready: true,
      remaining_minutes: 0,
      incomplete_required_requirements: [],
      blockers: [],
    }
    ojt.getStudentOverview.mockResolvedValue(data)

    const wrapper = mount(OverviewView, { global })
    await flushPromises()

    expect(wrapper.get('[data-testid="overview-completion-status"]').text()).toContain('Ready')
    expect(wrapper.get('[data-testid="overview-completion"]').text()).toContain('Required OJT hours reached')
    expect(wrapper.get('[data-testid="overview-completion"]').text()).toContain('All required internship requirements completed')
    expect(wrapper.get('[data-testid="overview-completion"]').text()).toContain('Your tracked OJT requirements are complete.')
    expect(wrapper.text()).not.toContain('officially completed')
  })

  it('explains when pace cannot be calculated from the OJT setup', async () => {
    const data = overview()
    data.internship.work_days = []
    data.internship.expected_daily_minutes = null
    data.pace = {
      status: 'pace_unavailable',
      remaining_scheduled_days: 0,
      required_daily_minutes: null,
      expected_daily_minutes: null,
    }
    ojt.getStudentOverview.mockResolvedValue(data)

    const wrapper = mount(OverviewView, { global })
    await flushPromises()

    expect(wrapper.get('[data-testid="overview-status"]').text()).toContain('Pace unavailable')
    expect(wrapper.get('[data-testid="overview-pace-unavailable"]').text()).toContain('Your OJT work schedule is incomplete')
    expect(wrapper.get('[data-testid="overview-pace-unavailable"] a').attributes('href')).toBe('/student/profile')
    expect(wrapper.get('[data-testid="overview-pace"]').text()).not.toContain('above your expected pace')
  })

  it('shows a clear empty attention state', async () => {
    const data = overview()
    data.attention = { items: [], additional_count: 0 }
    ojt.getStudentOverview.mockResolvedValue(data)

    const wrapper = mount(OverviewView, { global })
    await flushPromises()

    expect(wrapper.get('[data-testid="overview-attention-empty"]').text()).toContain('Nothing urgent right now.')
    expect(wrapper.get('[data-testid="overview-attention-empty"]').text()).toContain('Keep logging your OJT hours')
  })

  it('shows loading and recovers from a safe retryable error', async () => {
    let rejectRequest
    ojt.getStudentOverview.mockReturnValueOnce(new Promise((_, reject) => { rejectRequest = reject }))
    const wrapper = mount(OverviewView, { global })

    expect(wrapper.get('[data-testid="overview-loading"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="overview-export"]').exists()).toBe(false)
    rejectRequest(new Error('AxiosError: database details'))
    await flushPromises()

    expect(wrapper.get('[data-testid="overview-error"]').text()).toContain("We couldn't load your OJT overview.")
    expect(wrapper.find('[data-testid="overview-export"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('AxiosError')

    ojt.getStudentOverview.mockResolvedValueOnce(overview())
    await wrapper.get('[data-testid="overview-retry"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-testid="overview-progress"]').exists()).toBe(true)
  })

  it('shows an empty state when no internship is configured', async () => {
    ojt.getStudentOverview.mockRejectedValue({ response: { status: 404 } })

    const wrapper = mount(OverviewView, { global })
    await flushPromises()

    expect(wrapper.get('[data-testid="overview-empty"]').text()).toContain('No OJT setup found')
    expect(wrapper.get('[data-testid="overview-empty"]').text()).toContain('Complete your OJT setup before using the progress assistant.')
    expect(wrapper.find('[data-testid="overview-error"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="overview-export"]').exists()).toBe(false)
  })

  it('downloads one PDF and revokes its temporary object URL', async () => {
    const response = pdfResponse()
    const download = stubDownload()
    ojt.exportStudentSummary.mockResolvedValue(response)
    const wrapper = mount(OverviewView, { global })
    await flushPromises()
    const timeoutSpy = vi.spyOn(globalThis, 'setTimeout')

    await wrapper.get('[data-testid="overview-export"]').trigger('click')
    await flushPromises()

    expect(ojt.exportStudentSummary).toHaveBeenCalledTimes(1)
    expect(download.createObjectURL).toHaveBeenCalledWith(response.data)
    expect(download.clicks).toEqual([{
      href: 'blob:ojt-summary',
      download: 'OJT-Progress-Summary-2026-09-24.pdf',
    }])
    const revokeTimer = timeoutSpy.mock.calls.find(([, delay]) => delay === 1000)
    expect(revokeTimer).toBeDefined()
    revokeTimer[0]()
    expect(download.revokeObjectURL).toHaveBeenCalledWith('blob:ojt-summary')
  })

  it('disables repeated clicks while the PDF request is pending', async () => {
    let resolveExport
    const response = pdfResponse()
    const download = stubDownload()
    ojt.exportStudentSummary.mockReturnValue(new Promise((resolve) => { resolveExport = resolve }))
    const wrapper = mount(OverviewView, { global })
    await flushPromises()
    const button = wrapper.get('[data-testid="overview-export"]')

    button.element.click()
    button.element.click()
    await nextTick()

    expect(ojt.exportStudentSummary).toHaveBeenCalledTimes(1)
    expect(button.text()).toBe('Generating…')
    expect(button.attributes('disabled')).toBeDefined()

    resolveExport(response)
    await flushPromises()

    expect(button.attributes('disabled')).toBeUndefined()
    expect(download.clicks).toHaveLength(1)
  })

  it('shows a generic retry error and succeeds on the next attempt', async () => {
    const download = stubDownload()
    ojt.exportStudentSummary
      .mockRejectedValueOnce(new Error('database password leaked'))
      .mockResolvedValueOnce(pdfResponse())
    const wrapper = mount(OverviewView, { global })
    await flushPromises()
    const button = wrapper.get('[data-testid="overview-export"]')

    await button.trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-testid="overview-export-error"]').text()).toBe(
      "We couldn't generate your OJT summary. Please try again.",
    )
    expect(wrapper.find('[role="alert"]').text()).not.toContain('database password leaked')
    expect(button.attributes('disabled')).toBeUndefined()

    await button.trigger('click')
    await flushPromises()

    expect(ojt.exportStudentSummary).toHaveBeenCalledTimes(2)
    expect(download.clicks).toHaveLength(1)
    expect(wrapper.find('[data-testid="overview-export-error"]').exists()).toBe(false)
  })

  it('uses a Philippine-date fallback filename when the header is unavailable', async () => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-09-23T18:30:00.000Z'))
    const download = stubDownload()
    ojt.exportStudentSummary.mockResolvedValue(pdfResponse({ 'content-type': 'application/pdf' }))
    const wrapper = mount(OverviewView, { global })
    await flushPromises()

    await wrapper.get('[data-testid="overview-export"]').trigger('click')
    await flushPromises()

    expect(download.clicks[0].download).toBe('OJT-Progress-Summary-2026-09-24.pdf')
  })
})
