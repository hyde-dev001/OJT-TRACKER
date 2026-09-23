import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const stylesheet = readFileSync(resolve(process.cwd(), 'src/assets/main.css'), 'utf8')

describe('dark theme day selectors', () => {
  it('keeps selected OJT day labels readable in registration and profile', () => {
    const selectedDayRule = stylesheet.match(
      /html\[data-theme='dark'\] \.register-day:has\(input:checked\)[\s\S]*?\}/,
    )?.[0] ?? ''

    expect(selectedDayRule).toContain('.profile-day:has(input:checked)')
    expect(selectedDayRule).toContain('background: #f8fafc !important;')
    expect(selectedDayRule).toContain('color: #111827 !important;')
  })
})
