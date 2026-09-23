import { beforeEach, describe, expect, it, vi } from 'vitest'
import { initializeTheme, setTheme, toggleTheme, useTheme } from './useTheme'

const setSystemTheme = (matches) => {
  vi.stubGlobal('matchMedia', vi.fn(() => ({ matches })))
}

const createStorage = () => {
  const values = new Map()
  return {
    getItem: (key) => values.get(key) ?? null,
    setItem: (key, value) => values.set(key, String(value)),
    removeItem: (key) => values.delete(key),
    clear: () => values.clear(),
  }
}

describe('useTheme', () => {
  beforeEach(() => {
    vi.stubGlobal('localStorage', createStorage())
    delete document.documentElement.dataset.theme
    setSystemTheme(false)
    initializeTheme()
  })

  it('uses the saved preference before the system preference', () => {
    localStorage.setItem('ojt-theme', 'dark')
    setSystemTheme(false)

    expect(initializeTheme()).toBe('dark')
    expect(useTheme().theme.value).toBe('dark')
    expect(document.documentElement.dataset.theme).toBe('dark')
  })

  it('uses the system preference when no saved preference exists', () => {
    setSystemTheme(true)

    expect(initializeTheme()).toBe('dark')
    expect(document.documentElement.dataset.theme).toBe('dark')
  })

  it('toggles and persists the selected theme', () => {
    initializeTheme()

    expect(toggleTheme()).toBe('dark')
    expect(localStorage.getItem('ojt-theme')).toBe('dark')
    expect(document.documentElement.dataset.theme).toBe('dark')

    setTheme('light')
    expect(useTheme().theme.value).toBe('light')
    expect(localStorage.getItem('ojt-theme')).toBe('light')
    expect(document.documentElement.dataset.theme).toBe('light')
  })
})
