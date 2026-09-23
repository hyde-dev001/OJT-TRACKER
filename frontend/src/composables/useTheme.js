import { computed, ref } from 'vue'

export const THEME_STORAGE_KEY = 'ojt-theme'

const theme = ref('light')
let initialized = false

const isTheme = (value) => value === 'light' || value === 'dark'

const systemTheme = () => (
  typeof window !== 'undefined'
    && window.matchMedia?.('(prefers-color-scheme: dark)').matches
    ? 'dark'
    : 'light'
)

const applyTheme = (value) => {
  const nextTheme = isTheme(value) ? value : 'light'
  theme.value = nextTheme

  if (typeof document !== 'undefined') {
    document.documentElement.dataset.theme = nextTheme
    document.documentElement.style.colorScheme = nextTheme
  }

  return nextTheme
}

export function initializeTheme() {
  let savedTheme = null

  try {
    savedTheme = localStorage.getItem(THEME_STORAGE_KEY)
  } catch {
    savedTheme = null
  }

  initialized = true
  return applyTheme(isTheme(savedTheme) ? savedTheme : systemTheme())
}

export function setTheme(value) {
  const nextTheme = applyTheme(value)
  initialized = true

  try {
    localStorage.setItem(THEME_STORAGE_KEY, nextTheme)
  } catch {
    // Theme still applies for this session when storage is unavailable.
  }

  return nextTheme
}

export function toggleTheme() {
  return setTheme(theme.value === 'dark' ? 'light' : 'dark')
}

export function useTheme() {
  if (!initialized) initializeTheme()

  return {
    theme,
    isDark: computed(() => theme.value === 'dark'),
    initializeTheme,
    setTheme,
    toggleTheme,
  }
}
