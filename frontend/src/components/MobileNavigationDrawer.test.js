import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import MobileNavigationDrawer from './MobileNavigationDrawer.vue'

const RouterLinkStub = {
  props: ['to'],
  template: '<a :href="to"><slot /></a>',
}

describe('MobileNavigationDrawer', () => {
  it('offers separate company registration on public pages', () => {
    const wrapper = mount(MobileNavigationDrawer, {
      props: { open: true, authenticated: false },
      global: { stubs: { RouterLink: RouterLinkStub } },
    })

    expect(wrapper.get('a[href="/register/company"]').text()).toContain('Register company account')
  })

  it.each([
    ['company', '/company/interns', 'Intern accounts'],
    ['intern', '/intern/account', 'OJT account'],
  ])('shows %s users only their workspace link', (role, destination, label) => {
    const wrapper = mount(MobileNavigationDrawer, {
      props: {
        open: true,
        authenticated: true,
        user: { id: 1, name: 'Account', role },
      },
      global: { stubs: { RouterLink: RouterLinkStub } },
    })

    expect(wrapper.get(`a[href="${destination}"]`).text()).toContain(label)
    expect(wrapper.find('a[href="/student/overview"]').exists()).toBe(false)
  })

  it('shows company attendance alongside intern accounts', () => {
    const wrapper = mount(MobileNavigationDrawer, {
      props: {
        open: true,
        authenticated: true,
        user: { id: 1, name: 'Company', role: 'company' },
      },
      global: { stubs: { RouterLink: RouterLinkStub } },
    })

    expect(wrapper.get('a[href="/company/interns"]').text()).toContain('Intern accounts')
    expect(wrapper.get('a[href="/company/attendance"]').text()).toContain('Attendance')
    expect(wrapper.get('a[href="/company/tasks"]').text()).toContain('Tasks')
    expect(wrapper.get('a[href="/company/requirements"]').text()).toContain('Requirements')
    expect(wrapper.find('a[href="/student/work-hours"]').exists()).toBe(false)
  })

  it('shows read-only Work Hours alongside the intern account page', () => {
    const wrapper = mount(MobileNavigationDrawer, {
      props: {
        open: true,
        authenticated: true,
        user: { id: 2, name: 'Taylor', role: 'intern' },
      },
      global: { stubs: { RouterLink: RouterLinkStub } },
    })

    expect(wrapper.get('a[href="/intern/work-hours"]').text()).toContain('Work Hours')
    expect(wrapper.get('a[href="/intern/account"]').text()).toContain('OJT account')
    expect(wrapper.get('a[href="/intern/tasks"]').text()).toContain('Tasks')
    expect(wrapper.get('a[href="/intern/requirements"]').text()).toContain('Requirements')
    expect(wrapper.find('a[href="/student/work-hours"]').exists()).toBe(false)
  })
})
