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
    auth.user = { id: 1, name: 'Student', role: 'student' }

    await router.push('/login')

    expect(router.currentRoute.value.name).toBe('student-overview')
  })

  it('returns an authenticated user from the public root to the student overview', async () => {
    const auth = useAuthStore()
    auth.user = { id: 1, name: 'Student', role: 'student' }

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

  it('exposes company registration as a separate public route', async () => {
    await router.push('/register/company')

    expect(router.currentRoute.value.name).toBe('company-register')
  })

  it.each([
    ['company', '/company/interns'],
    ['intern', '/intern/overview'],
  ])('redirects a %s account away from student routes', async (role, destination) => {
    const auth = useAuthStore()
    auth.user = { id: 2, name: 'Account', role }

    await router.push('/student/overview')

    expect(router.currentRoute.value.path).toBe(destination)
  })

  it('marks student pages for student accounts only', () => {
    expect(router.resolve('/student/overview').meta.roles).toEqual(['student'])
  })

  it('protects company intern management with the company account role', async () => {
    const auth = useAuthStore()
    auth.user = { id: 3, name: 'Company', role: 'company' }

    await router.push('/company/interns')
    expect(router.currentRoute.value.name).toBe('company-interns')
    expect(router.currentRoute.value.meta.roles).toEqual(['company'])

    await router.push('/about')
    auth.user = { id: 1, name: 'Student', role: 'student' }
    await router.push('/company/interns')

    expect(router.currentRoute.value.name).toBe('student-overview')
  })

  it('protects company attendance with the company account role', async () => {
    const auth = useAuthStore()
    auth.user = { id: 3, name: 'Company', role: 'company' }

    await router.push('/company/attendance')
    expect(router.currentRoute.value.name).toBe('company-attendance')
    expect(router.currentRoute.value.meta.roles).toEqual(['company'])

    await router.push('/about')
    auth.user = { id: 1, name: 'Student', role: 'student' }
    await router.push('/company/attendance')

    expect(router.currentRoute.value.name).toBe('student-overview')
  })

  it.each([
    ['company-tasks', '/company/tasks', 'company', '/student/overview'],
    ['company-requirements', '/company/requirements', 'company', '/student/overview'],
    ['intern-tasks', '/intern/tasks', 'intern', '/student/overview'],
    ['intern-requirements', '/intern/requirements', 'intern', '/student/overview'],
  ])('protects %s with its account role', async (name, path, role, otherWorkspace) => {
    const auth = useAuthStore()
    auth.user = { id: 3, name: 'Account', role }

    await router.push(path)
    expect(router.currentRoute.value.name).toBe(name)
    expect(router.currentRoute.value.meta.roles).toEqual([role])

    await router.push('/about')
    auth.user = { id: 1, name: 'Student', role: 'student' }
    await router.push(path)

    expect(router.currentRoute.value.path).toBe(otherWorkspace)
  })

  it('protects the intern account page with the intern account role', async () => {
    const auth = useAuthStore()
    auth.user = { id: 2, name: 'Taylor', role: 'intern' }

    await router.push('/intern/account')

    expect(router.currentRoute.value.name).toBe('intern-account')
    expect(router.currentRoute.value.meta.roles).toEqual(['intern'])
  })

  it('keeps interns with a temporary password on account setup until it is changed', async () => {
    const auth = useAuthStore()
    auth.user = { id: 2, name: 'Taylor', role: 'intern', must_change_password: true }

    await router.push('/intern/overview')

    expect(router.currentRoute.value.path).toBe('/intern/account')

    await router.push('/login')

    expect(router.currentRoute.value.path).toBe('/intern/account')
  })

  it('protects the intern overview page with the intern account role', async () => {
    const auth = useAuthStore()
    auth.user = { id: 2, name: 'Taylor', role: 'intern' }

    await router.push('/intern/overview')

    expect(router.currentRoute.value.name).toBe('intern-overview')
    expect(router.currentRoute.value.meta.roles).toEqual(['intern'])
  })

  it('protects the company account password page with the company role', async () => {
    const auth = useAuthStore()
    auth.user = { id: 3, name: 'Company', role: 'company' }

    await router.push('/company/account')
    expect(router.currentRoute.value.name).toBe('company-account')
    expect(router.currentRoute.value.meta.roles).toEqual(['company'])

    await router.push('/about')
    auth.user = { id: 1, name: 'Student', role: 'student' }
    await router.push('/company/account')

    expect(router.currentRoute.value.name).toBe('student-overview')
  })

  it('protects the intern Work Hours page with the intern account role', async () => {
    const auth = useAuthStore()
    auth.user = { id: 2, name: 'Taylor', role: 'intern' }

    await router.push('/intern/work-hours')

    expect(router.currentRoute.value.name).toBe('intern-work-hours')
    expect(router.currentRoute.value.meta.roles).toEqual(['intern'])

    await router.push('/about')
    auth.user = { id: 1, name: 'Student', role: 'student' }
    await router.push('/intern/work-hours')

    expect(router.currentRoute.value.name).toBe('student-overview')
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
