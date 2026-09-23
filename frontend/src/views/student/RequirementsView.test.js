import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import RequirementsView from './RequirementsView.vue'
import * as ojt from '../../services/ojt'

vi.mock('../../services/ojt', () => ({
  getStudentInternship: vi.fn(),
  listStudentRequirements: vi.fn(),
  createRequirement: vi.fn(),
  updateRequirement: vi.fn(),
  deleteRequirement: vi.fn(),
  completeRequirement: vi.fn(),
  incompleteRequirement: vi.fn(),
}))

const page = (items = [], overrides = {}) => ({
  items,
  links: {},
  meta: { current_page: 1, last_page: 1, per_page: 10, total: items.length, from: items.length ? 1 : null, to: items.length || null, ...overrides },
})

describe('student RequirementsView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    ojt.getStudentInternship.mockResolvedValue({ id: 12, start_date: '2026-09-01', end_date: '2099-12-31' })
    ojt.listStudentRequirements.mockResolvedValue(page([
      { id: 1, title: 'Incomplete requirement', is_required: true, due_date: '2026-09-20', status: 'incomplete', student_notes: 'Bring a copy.', is_overdue: true },
      { id: 2, title: 'Completed requirement', is_required: false, due_date: '2026-09-30', status: 'completed', student_notes: 'Done.', is_overdue: false },
    ]))
    ojt.createRequirement.mockResolvedValue({})
    ojt.updateRequirement.mockResolvedValue({})
    ojt.deleteRequirement.mockResolvedValue({})
    ojt.completeRequirement.mockResolvedValue({})
    ojt.incompleteRequirement.mockResolvedValue({})
  })

  it('shows personal completion state without review language', async () => {
    const wrapper = mount(RequirementsView)
    await flushPromises()

    expect(wrapper.text()).toContain('Incomplete requirement')
    expect(wrapper.text()).toContain('Sep 20, 2026')
    expect(wrapper.text()).toContain('Required')
    expect(wrapper.get('[data-testid="complete-1"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="incomplete-2"]').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('Submit')
    expect(wrapper.text()).not.toContain('Approved')
    expect(wrapper.text()).not.toContain('Coordinator')
  })

  it('requests the selected requirement filter and preserves it while paging', async () => {
    ojt.listStudentRequirements
      .mockResolvedValueOnce(page([
        { id: 1, title: 'Incomplete requirement', status: 'incomplete', is_required: true, is_overdue: true },
      ], { last_page: 2, total: 11, to: 10 }))
      .mockResolvedValueOnce(page([
        { id: 4, title: 'Overdue requirement', status: 'incomplete', is_required: true, is_overdue: true },
      ], { last_page: 2, total: 11, to: 10 }))
      .mockResolvedValueOnce(page([
        { id: 5, title: 'Second page overdue requirement', status: 'incomplete', is_required: true, is_overdue: true },
      ], { current_page: 2, last_page: 2, total: 11, from: 11, to: 11 }))
    const wrapper = mount(RequirementsView)
    await flushPromises()

    expect(ojt.listStudentRequirements).toHaveBeenCalledWith({ page: 1, filter: 'all' })
    await wrapper.get('[data-testid="filter-overdue"]').trigger('click')
    await flushPromises()
    expect(ojt.listStudentRequirements).toHaveBeenLastCalledWith({ page: 1, filter: 'overdue' })

    await wrapper.get('[data-page="2"]').trigger('click')
    await flushPromises()
    expect(ojt.listStudentRequirements).toHaveBeenLastCalledWith({ page: 2, filter: 'overdue' })
  })

  it('shows a no-match state for a filter with no requirements', async () => {
    ojt.listStudentRequirements.mockResolvedValueOnce(page([])).mockResolvedValueOnce(page([]))
    const wrapper = mount(RequirementsView)
    await flushPromises()
    await wrapper.get('[data-testid="filter-overdue"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-testid="requirements-filter-empty"]').text()).toContain('No requirements match this filter')
  })

  it('creates, edits, toggles, and deletes a requirement', async () => {
    const wrapper = mount(RequirementsView)
    await flushPromises()

    await wrapper.get('[data-testid="add-requirement"]').trigger('click')
    document.body.querySelector('#requirement-title').value = 'New requirement'
    document.body.querySelector('#requirement-title').dispatchEvent(new Event('input', { bubbles: true }))
    document.body.querySelector('[data-testid="requirement-form"]').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
    await flushPromises()
    expect(ojt.createRequirement).toHaveBeenCalledWith(expect.objectContaining({ internship_id: 12, title: 'New requirement' }))
    expect(document.body.querySelector('[data-testid="action-alert"]')).not.toBeNull()
    document.body.querySelector('[data-action="alert-close"]').click()

    await wrapper.get('[data-testid="complete-1"]').trigger('click')
    await flushPromises()
    expect(ojt.completeRequirement).toHaveBeenCalledWith(1)

    await wrapper.get('[data-testid="incomplete-2"]').trigger('click')
    await flushPromises()
    expect(ojt.incompleteRequirement).toHaveBeenCalledWith(2)

    await wrapper.get('[data-testid="delete-1"]').trigger('click')
    document.body.querySelector('[data-action="confirm"]').click()
    await flushPromises()
    expect(ojt.deleteRequirement).toHaveBeenCalledWith(1)
  })

  it('limits requirement due dates to the internship period and today', async () => {
    const wrapper = mount(RequirementsView)
    await flushPromises()

    await wrapper.get('[data-testid="add-requirement"]').trigger('click')

    const dateField = document.body.querySelector('#requirement-due-date')
    expect(dateField.getAttribute('type')).toBe('text')
    expect(dateField.getAttribute('data-min')).toBe('2026-09-01')
    expect(dateField.getAttribute('data-max')).toBe('2099-12-31')
    dateField.click()
    await flushPromises()
    const calendar = document.body.querySelector('[data-testid="requirement-due-date-calendar"]')
    expect(calendar).not.toBeNull()
    expect(calendar.querySelector('[data-testid="calendar-day-2026-08-31"]').disabled).toBe(true)
    expect(calendar.querySelector('[data-testid="calendar-day-2026-09-01"]').disabled).toBe(false)
  })
})
