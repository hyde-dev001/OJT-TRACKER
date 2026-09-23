import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { internshipDateBounds } from './dateBounds'

describe('internshipDateBounds', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-09-22T16:30:00.000Z'))
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('uses the Philippine calendar date for today limits', () => {
    expect(internshipDateBounds({
      start_date: '2026-09-20',
      end_date: '2026-09-30',
    }).max).toBe('2026-09-23')
  })
})
