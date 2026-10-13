import { afterEach, expect, it, vi } from 'vitest'

afterEach(() => {
  vi.unstubAllEnvs()
  vi.resetModules()
})

it('uses the current local hostname for the API so session cookies are shared', async () => {
  vi.stubEnv('VITE_API_BASE_URL', 'http://127.0.0.1:8000/api')

  const { default: api } = await import('./api')

  expect(api.defaults.baseURL).toBe(`http://${window.location.hostname}:8000/api`)
})
