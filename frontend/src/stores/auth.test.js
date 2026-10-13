import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from './auth'
import api, { ensureCsrfCookie } from '../services/api'

vi.mock('../services/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
  },
  ensureCsrfCookie: vi.fn(),
  apiErrorMessage: vi.fn(() => 'The API is unavailable. Check the connection and try again.'),
}))

describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  it('restores the current student from the API', async () => {
    api.get.mockResolvedValue({
      data: { user: { id: 1, name: 'Student', email: 'student@example.com', role: 'student', must_change_password: false } },
    })

    const store = useAuthStore()
    await store.restore()

    expect(store.user.name).toBe('Student')
    expect(store.isAuthenticated).toBe(true)
    expect(store.user.role).toBe('student')
    expect(store.user.must_change_password).toBe(false)
  })

  it('bootstraps CSRF before logging in', async () => {
    ensureCsrfCookie.mockResolvedValue({})
    api.post.mockResolvedValue({
      data: { user: { id: 1, name: 'Student', email: 'student@example.com' } },
    })

    const store = useAuthStore()
    await store.login({ email: 'student@example.com', password: 'secret' })

    expect(ensureCsrfCookie).toHaveBeenCalledOnce()
    expect(api.post).toHaveBeenCalledWith('/login', {
      email: 'student@example.com',
      password: 'secret',
    })
    expect(ensureCsrfCookie.mock.invocationCallOrder[0])
      .toBeLessThan(api.post.mock.invocationCallOrder[0])
  })

  it('bootstraps CSRF before registering a student', async () => {
    ensureCsrfCookie.mockResolvedValue({})
    api.post.mockResolvedValue({
      data: { user: { id: 2, name: 'New Student', email: 'new@example.com' } },
    })

    const store = useAuthStore()
    await store.register({ name: 'New Student', email: 'new@example.com' })

    expect(api.post).toHaveBeenCalledWith('/register', { name: 'New Student', email: 'new@example.com' })
    expect(store.user.email).toBe('new@example.com')
  })

  it('bootstraps CSRF before registering a company account', async () => {
    ensureCsrfCookie.mockResolvedValue({})
    api.post.mockResolvedValue({
      data: { user: { id: 3, name: 'Jamie Santos', email: 'owner@example.com', role: 'company' } },
    })
    const store = useAuthStore()
    const details = {
      company_name: 'Northwind Studio',
      first_name: 'Jamie',
      last_name: 'Santos',
      email: 'owner@example.com',
      password: 'StrongPassword1!',
      password_confirmation: 'StrongPassword1!',
    }

    await store.registerCompany(details)

    expect(ensureCsrfCookie).toHaveBeenCalledOnce()
    expect(api.post).toHaveBeenCalledWith('/company/register', details)
    expect(store.user.role).toBe('company')
    expect(store.bootstrapped).toBe(true)
  })

  it('changes an intern password and stores the returned setup state', async () => {
    ensureCsrfCookie.mockResolvedValue({})
    api.put.mockResolvedValue({
      data: { user: { id: 8, role: 'intern', must_change_password: false } },
    })
    const store = useAuthStore()
    store.user = { id: 8, name: 'Taylor Lee', role: 'intern', must_change_password: true }
    const details = {
      current_password: 'TemporaryPass1!',
      password: 'PermanentPassword2!',
      password_confirmation: 'PermanentPassword2!',
    }

    await store.changeInternPassword(details)

    expect(ensureCsrfCookie).toHaveBeenCalledOnce()
    expect(api.put).toHaveBeenCalledWith('/intern/password', details)
    expect(store.user).toEqual({ id: 8, name: 'Taylor Lee', role: 'intern', must_change_password: false })
  })

  it('updates the authenticated profile', async () => {
    ensureCsrfCookie.mockResolvedValue({})
    api.put.mockResolvedValue({
      data: { user: { id: 1, name: 'Updated Student', email: 'student@example.com' } },
    })

    const store = useAuthStore()
    store.user = { id: 1, name: 'Student', email: 'student@example.com' }
    const details = {
      name: 'Updated Student',
      start_date: '2026-10-01',
      end_date: '2027-01-01',
      current_password: 'secret-password',
      password: 'NewStrongPassword1!',
      password_confirmation: 'NewStrongPassword1!',
    }

    await store.updateProfile(details)

    expect(ensureCsrfCookie).toHaveBeenCalledOnce()
    expect(api.put).toHaveBeenCalledWith('/profile', details)
    expect(store.user.name).toBe('Updated Student')
  })

  it('keeps the session when logout fails', async () => {
    const store = useAuthStore()
    store.user = { id: 1, name: 'Student' }
    api.post.mockRejectedValue(new Error('offline'))

    await expect(store.logout()).resolves.toBe(false)

    expect(store.user).toEqual({ id: 1, name: 'Student' })
    expect(store.isAuthenticated).toBe(true)
  })

  it('clears local identity after logout succeeds', async () => {
    const store = useAuthStore()
    store.user = { id: 1, name: 'Student' }
    api.post.mockResolvedValue({})

    await expect(store.logout()).resolves.toBe(true)

    expect(store.user).toBeNull()
    expect(store.isAuthenticated).toBe(false)
  })
})
