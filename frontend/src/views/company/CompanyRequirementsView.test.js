import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import CompanyRequirementsView from './CompanyRequirementsView.vue'
import {
  approveCompanyRequirement,
  createCompanyInternRequirement,
  downloadCompanyRequirementProof,
  getCompanyInternRequirement,
  listCompanyInterns,
  listCompanyInternRequirements,
  requestCompanyRequirementChanges,
} from '../../services/ojt'

const route = vi.hoisted(() => ({ query: {}, hash: '' }))
const routerReplace = vi.hoisted(() => vi.fn())

vi.mock('vue-router', () => ({
  useRoute: () => route,
  useRouter: () => ({ replace: routerReplace }),
}))

vi.mock('../../services/ojt', () => ({
  approveCompanyRequirement: vi.fn(),
  createCompanyInternRequirement: vi.fn(),
  downloadCompanyRequirementProof: vi.fn(),
  getCompanyInternRequirement: vi.fn(),
  listCompanyInterns: vi.fn(),
  listCompanyInternRequirements: vi.fn(),
  requestCompanyRequirementChanges: vi.fn(),
}))

vi.mock('../../services/api', () => ({
  apiErrorMessage: vi.fn(() => 'Unable to reach the API.'),
}))

enableAutoUnmount(afterEach)

const intern = { id: 7, name: 'Taylor Lee', email: 'taylor.lee@example.com' }
const requirement = {
  id: 4,
  title: 'Submit agreement',
  description: 'Signed training agreement.',
  due_date: '2026-10-20',
  is_required: true,
  status: 'incomplete',
  submission_status: 'pending_review',
  submission_comment: 'Signed copy attached.',
  review_comment: null,
  proof_available: true,
}
const page = (items = []) => ({
  items,
  links: {},
  meta: { current_page: 1, last_page: 1, per_page: 10, total: items.length, from: items.length ? 1 : null, to: items.length || null },
})

const DatePickerFieldStub = {
  props: ['id', 'modelValue', 'invalid', 'describedby'],
  emits: ['update:modelValue'],
  template: '<input :id="id" :value="modelValue" :aria-invalid="invalid" @input="$emit(\'update:modelValue\', $event.target.value)">',
}

const setBodyInputValue = async (selector, value) => {
  const input = document.body.querySelector(selector)
  input.value = value
  input.dispatchEvent(new Event('input', { bubbles: true }))
  await flushPromises()
}

const submitBodyForm = async (selector) => {
  document.body.querySelector(selector).dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
  await flushPromises()
}

const mountView = () => mount(CompanyRequirementsView, {
  global: {
    stubs: {
      DatePickerField: DatePickerFieldStub,
      PaginationControls: { props: ['meta'], template: '<div data-testid="pagination-meta">{{ meta && meta.total }}</div>' },
      PageHeader: { props: ['title', 'description'], template: '<header><h1>{{ title }}</h1><p>{{ description }}</p></header>' },
      StatusBadge: { props: ['status'], template: '<span>{{ status }}</span>' },
    },
  },
})

describe('CompanyRequirementsView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    route.query = {}
    route.hash = ''
  })
  afterEach(() => vi.unstubAllGlobals())

  it('shows an empty state when the company has no intern accounts', async () => {
    listCompanyInterns.mockResolvedValueOnce([])
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.text()).toContain('No intern accounts yet')
    expect(listCompanyInternRequirements).not.toHaveBeenCalled()
  })

  it('loads the selected intern requirements and displays pending proof state', async () => {
    listCompanyInterns.mockResolvedValueOnce([intern])
    listCompanyInternRequirements.mockResolvedValueOnce(page([requirement]))
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.get('#company-requirement-intern-select').element.value).toBe('7')
    expect(listCompanyInternRequirements).toHaveBeenCalledWith(7, { page: 1 })
    expect(wrapper.get('[data-testid="company-requirement-4"]').text()).toContain('Pending review')
    expect(wrapper.get('[data-testid="company-requirement-4"]').text()).toContain('Signed copy attached.')
    expect(wrapper.find('[data-testid="edit-company-requirement-4"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="delete-company-requirement-4"]').exists()).toBe(false)
  })

  it('selects the notification intern and shows an exact off-page requirement with proof controls', async () => {
    const otherIntern = { id: 8, name: 'Alex Morgan', email: 'alex@example.com' }
    const target = { ...requirement, id: 99, title: 'Exact submitted requirement', proof_available: true }
    route.query = { intern_id: '8', requirement_id: '99' }
    listCompanyInterns.mockResolvedValueOnce([intern, otherIntern])
    listCompanyInternRequirements.mockResolvedValueOnce(page([requirement]))
    getCompanyInternRequirement.mockResolvedValueOnce(target)
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.get('#company-requirement-intern-select').element.value).toBe('8')
    expect(getCompanyInternRequirement).toHaveBeenCalledWith(8, 99)
    expect(wrapper.get('[data-testid="company-requirement-99"]').text()).toContain('Exact submitted requirement')
    expect(wrapper.get('[data-testid="company-requirement-99"]').attributes('data-notification-target')).toBe('true')
    expect(wrapper.get('[data-testid="view-requirement-proof-99"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="pagination-meta"]').text()).toBe('1')
    expect(routerReplace).toHaveBeenCalledWith({ query: route.query, hash: '#company-requirement-99' })
    wrapper.unmount()
  })

  it('shows a recoverable message when the notification requirement cannot be loaded', async () => {
    route.query = { intern_id: '7', requirement_id: '404' }
    listCompanyInterns.mockResolvedValueOnce([intern])
    listCompanyInternRequirements.mockResolvedValueOnce(page())
    getCompanyInternRequirement.mockRejectedValueOnce(new Error('missing'))
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.get('[data-testid="company-requirement-target-error"]').text()).toContain('Unable to reach the API')
    expect(wrapper.get('[data-testid="company-requirement-target-error"] a').attributes('href')).toBe('/company/requirements')
    wrapper.unmount()
  })

  it('creates a requirement with required defaulting to true and supports an optional flag', async () => {
    listCompanyInterns.mockResolvedValueOnce([intern])
    listCompanyInternRequirements.mockResolvedValue(page())
    createCompanyInternRequirement.mockResolvedValueOnce({ id: 9 })
    const wrapper = mountView()
    await flushPromises()

    expect(document.body.querySelector('[data-testid="company-requirement-form"]')).toBeNull()
    await wrapper.get('[data-testid="assign-requirement-open"]').trigger('click')
    expect(document.body.querySelector('[role="dialog"]')?.textContent).toContain('Assign a requirement')
    await setBodyInputValue('#company-requirement-title', 'Submit agreement')
    await setBodyInputValue('#company-requirement-description', 'Signed training agreement.')
    await setBodyInputValue('#company-requirement-due-date', '2026-10-20')
    await submitBodyForm('[data-testid="company-requirement-form"]')
    await flushPromises()

    expect(createCompanyInternRequirement).toHaveBeenCalledWith(7, {
      title: 'Submit agreement',
      description: 'Signed training agreement.',
      due_date: '2026-10-20',
      is_required: true,
    })
    expect(document.body.textContent).toContain('Requirement assigned')
    expect(document.body.querySelector('[data-testid="company-requirement-form"]')).toBeNull()
  })

  it('requires a comment for changes, allows approval without a comment, and downloads proof through the service', async () => {
    listCompanyInterns.mockResolvedValueOnce([intern])
    listCompanyInternRequirements.mockResolvedValue(page([requirement]))
    requestCompanyRequirementChanges.mockResolvedValueOnce({ ...requirement, submission_status: 'changes_requested' })
    approveCompanyRequirement.mockResolvedValueOnce({ ...requirement, submission_status: 'approved', status: 'completed' })
    vi.stubGlobal('URL', { createObjectURL: vi.fn(() => 'blob:company-requirement-proof'), revokeObjectURL: vi.fn() })
    downloadCompanyRequirementProof
      .mockResolvedValueOnce({ data: new Blob(['%PDF'], { type: 'application/pdf' }) })
      .mockRejectedValueOnce(new Error('offline'))
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.get('[data-testid="approve-requirement-4"]').element.disabled).toBe(true)
    expect(wrapper.text()).toContain('View the proof before approving.')

    await wrapper.get('[data-testid="request-changes-requirement-4"]').trigger('click')
    expect(requestCompanyRequirementChanges).not.toHaveBeenCalled()
    expect(wrapper.get('[data-testid="requirement-review-comment-error-4"]').text()).toContain('Add a comment')

    await wrapper.get('#requirement-review-comment-4').setValue('Please include the training date.')
    await wrapper.get('[data-testid="request-changes-requirement-4"]').trigger('click')
    await flushPromises()
    expect(requestCompanyRequirementChanges).toHaveBeenCalledWith(7, 4, { comment: 'Please include the training date.' })
    expect(document.body.textContent).toContain('Changes requested')
    document.body.querySelector('[data-testid="alert-close"]')?.click()

    await wrapper.get('[data-testid="view-requirement-proof-4"]').trigger('click')
    await flushPromises()
    expect(document.body.querySelector('[role="dialog"]')).not.toBeNull()
    expect(wrapper.get('[data-testid="approve-requirement-4"]').element.disabled).toBe(false)

    await wrapper.get('[data-testid="approve-requirement-4"]').trigger('click')
    await flushPromises()
    expect(approveCompanyRequirement).toHaveBeenCalledWith(7, 4, undefined)
    expect(document.body.textContent).toContain('Requirement approved')
    document.body.querySelector('[data-testid="alert-close"]')?.click()

    await wrapper.get('[data-testid="download-requirement-proof-4"]').trigger('click')
    await flushPromises()
    expect(downloadCompanyRequirementProof).toHaveBeenCalledWith(7, 4)
    expect(document.body.querySelector('[data-testid="action-alert"]')?.textContent).toContain('Unable to reach the API')
  })

  it('lets the company account holder preview private requirement proof before review', async () => {
    listCompanyInterns.mockResolvedValueOnce([intern])
    listCompanyInternRequirements.mockResolvedValueOnce(page([requirement]))
    vi.stubGlobal('URL', { createObjectURL: vi.fn(() => 'blob:company-requirement-proof'), revokeObjectURL: vi.fn() })
    downloadCompanyRequirementProof.mockResolvedValueOnce({ data: new Blob(['%PDF'], { type: 'application/pdf' }) })
    const wrapper = mountView()
    await flushPromises()

    await wrapper.get('[data-testid="view-requirement-proof-4"]').trigger('click')
    await flushPromises()

    expect(downloadCompanyRequirementProof).toHaveBeenCalledWith(7, 4)
    expect(document.body.querySelector('[role="dialog"]')).not.toBeNull()
    expect(document.body.querySelector('iframe')?.getAttribute('src')).toBe('blob:company-requirement-proof')
    expect(wrapper.get('[data-testid="approve-requirement-4"]').element.disabled).toBe(false)
    wrapper.unmount()
  })

  it('keeps approval disabled when the proof cannot be opened', async () => {
    listCompanyInterns.mockResolvedValueOnce([intern])
    listCompanyInternRequirements.mockResolvedValueOnce(page([requirement]))
    downloadCompanyRequirementProof.mockRejectedValueOnce(new Error('offline'))
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.get('[data-testid="approve-requirement-4"]').element.disabled).toBe(true)
    expect(wrapper.text()).toContain('View the proof before approving.')
    await wrapper.get('[data-testid="view-requirement-proof-4"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-testid="approve-requirement-4"]').element.disabled).toBe(true)
  })

  it('offers a retry when requirement loading fails', async () => {
    listCompanyInterns.mockResolvedValueOnce([intern])
    listCompanyInternRequirements.mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce(page())
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Unable to reach the API')
    await wrapper.get('[data-testid="retry-company-requirements"]').trigger('click')
    await flushPromises()

    expect(listCompanyInternRequirements).toHaveBeenCalledTimes(2)
    expect(wrapper.text()).toContain('No requirements assigned yet')
  })
})
