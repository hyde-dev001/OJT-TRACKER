import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import router from './index'
import { useAuthStore } from '../stores/auth'

describe('router guards', () => {
  beforeEach(async () => {
    setActivePinia(createPinia())
    const auth = useAuthStore()
    auth.user = null
    auth.bootstrapped = true
    await router.push('/')
  })

  it('redirects guests to login for student routes', async () => {
    await router.push('/student/work-hours')

    expect(router.currentRoute.value.name).toBe('login')
  })

  it('sends an authenticated user from login to the student application', async () => {
    const auth = useAuthStore()
    auth.user = { id: 1, name: 'Student' }

    await router.push('/login')

    expect(router.currentRoute.value.name).toBe('student-overview')
  })

  it('returns an authenticated user from the public root to the student overview', async () => {
    const auth = useAuthStore()
    auth.user = { id: 1, name: 'Student' }

    await router.push('/about')
    await router.push('/')

    expect(router.currentRoute.value.name).toBe('student-overview')
  })

  it('protects the student overview route', async () => {
    await router.push('/student/overview')

    expect(router.currentRoute.value.name).toBe('login')
  })

  it('exposes a public student registration route', async () => {
    const auth = useAuthStore()
    auth.user = null

    await router.push('/register')

    expect(router.currentRoute.value.name).toBe('register')
  })

  it('protects the student profile route', async () => {
    await router.push('/student/profile')

    expect(router.currentRoute.value.name).toBe('login')
  })

  it('does not expose coordinator routes', async () => {
    const auth = useAuthStore()
    auth.user = { id: 1, name: 'Student' }

    await router.push('/coordinator/work-hours')

    expect(router.currentRoute.value.name).toBe('not-found')
  })
})
