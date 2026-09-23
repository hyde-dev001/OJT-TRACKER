import { describe, expect, it } from 'vitest'
import { formatDate, formatDuration, formatTime } from './formatters'

describe('formatters', () => {
  it('formats API dates for people', () => {
    expect(formatDate('2026-09-04')).toBe('Sep 4, 2026')
  })

  it('formats API times for people', () => {
    expect(formatTime('08:00:00')).toBe('8:00 AM')
  })

  it('formats durations without exposing raw rendered minutes', () => {
    expect(formatDuration(510)).toBe('8h 30m')
    expect(formatDuration(480)).toBe('8h')
  })
})
