import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import DatePickerField from './DatePickerField.vue'

describe('DatePickerField', () => {
  it('opens a modal calendar and disables dates outside the configured rules', async () => {
    const wrapper = mount(DatePickerField, {
      props: {
        id: 'test-date',
        modelValue: '2026-09-01',
        min: '2026-09-01',
        max: '2026-09-30',
        allowedWeekdays: [1, 3, 5],
        testIdPrefix: 'test-date',
      },
      attachTo: document.body,
    })

    expect(wrapper.get('#test-date').attributes('type')).toBe('text')
    await wrapper.get('#test-date').trigger('click')
    await flushPromises()

    const calendar = document.body.querySelector('[data-testid="test-date-calendar"]')
    expect(calendar).not.toBeNull()
    expect(calendar.querySelector('[data-testid="test-date-previous"]').disabled).toBe(false)
    expect(calendar.querySelector('[data-testid="test-date-next"]').disabled).toBe(false)
    calendar.querySelector('[data-testid="test-date-next"]').click()
    await flushPromises()
    expect(calendar.querySelector('[aria-live="polite"]').textContent).toContain('October 2026')
    calendar.querySelector('[data-testid="test-date-previous"]').click()
    await flushPromises()
    expect(calendar.querySelector('[aria-live="polite"]').textContent).toContain('September 2026')
    expect(calendar.querySelector('[data-testid="calendar-day-2026-09-03"]').disabled).toBe(true)
    expect(calendar.querySelector('[data-testid="calendar-day-2026-09-04"]').disabled).toBe(false)

    calendar.querySelector('[data-testid="calendar-day-2026-09-04"]').click()
    await flushPromises()

    expect(wrapper.emitted('update:modelValue')).toEqual([['2026-09-04']])
    expect(document.body.querySelector('[data-testid="test-date-calendar"]')).toBeNull()
    wrapper.unmount()
  })

  it('keeps an existing in-range off-duty date editable without allowing an out-of-range date', async () => {
    const wrapper = mount(DatePickerField, {
      props: {
        id: 'existing-date',
        modelValue: '2026-09-03',
        min: '2026-09-01',
        max: '2026-09-30',
        allowedWeekdays: [1, 2, 3, 5],
        allowCurrentValue: true,
        testIdPrefix: 'existing-date',
      },
      attachTo: document.body,
    })

    await wrapper.get('#existing-date').trigger('click')
    await flushPromises()

    const calendar = document.body.querySelector('[data-testid="existing-date-calendar"]')
    expect(calendar.querySelector('[data-testid="calendar-day-2026-09-03"]').disabled).toBe(false)
    expect(calendar.querySelector('[data-testid="calendar-day-2026-08-31"]').disabled).toBe(true)
    wrapper.unmount()
  })
})
