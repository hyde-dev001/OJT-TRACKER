import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import CompanyRequirementsView from './CompanyRequirementsView.vue'
import {
  approveCompanyRequirement,
  createCompanyInternRequirement,
  downloadCompanyRequirementProof,
  listCompanyInterns,
  listCompanyInternRequirements,
  requestCompanyRequirementChanges,
} from '../../services/ojt'

vi.mock('../../services/ojt', () => ({
  approveCompanyRequirement: vi.fn(),
  createCompanyInternRequirement: vi.fn(),
  downloadCompanyRequirementProof: vi.fn(),
  listCompanyInterns: vi.fn(),
  listCompanyInternRequirements: vi.fn(),
  requestCompanyRequirementChanges: vi.fn(),
}))

vi.mock('../../services/api', () => ({
  apiErrorMessage: vi.fn(() => 'Unable to reach the API.'),
}))

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

const mountView = () => mount(CompanyRequirementsView, {
  global: {
    stubs: {
      DatePickerField: DatePickerFieldStub,
      PaginationControls: true,
      PageHeader: { props: ['title', 'description'], template: '<header><h1>{{ title }}</h1><p>{{ description }}</p></header>' },
      StatusBadge: { props: ['status'], template: '<span>{{ status }}</span>' },
    },
  },
})

describe('CompanyRequirementsView', () => {
  beforeEach(() => vi.resetAllMocks())

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

  it('creates a requirement with required defaulting to true and supports an optional flag', async () => {
    listCompanyInterns.mockResolvedValueOnce([intern])
    listCompanyInternRequirements.mockResolvedValue(page())
    createCompanyInternRequirement.mockResolvedValueOnce({ id: 9 })
    const wrapper = mountView()
    await flushPromises()

    await wrapper.get('#company-requirement-title').setValue('Submit agreement')
    await wrapper.get('#company-requirement-description').setValue('Signed training agreement.')
    await wrapper.get('#company-requirement-due-date').setValue('2026-10-20')
    await wrapper.get('[data-testid="company-requirement-form"]').trigger('submit')
    await flushPromises()

    expect(createCompanyInternRequirement).toHaveBeenCalledWith(7, {
      title: 'Submit agreement',
      description: 'Signed training agreement.',
      due_date: '2026-10-20',
      is_required: true,
    })
    expect(wrapper.text()).toContain('Requirement assigned')
  })

  it('requires a comment for changes, allows approval without a comment, and downloads proof through the service', async () => {
    listCompanyInterns.mockResolvedValueOnce([intern])
    listCompanyInternRequirements.mockResolvedValue(page([requirement]))
    requestCompanyRequirementChanges.mockResolvedValueOnce({ ...requirement, submission_status: 'changes_requested' })
    approveCompanyRequirement.mockResolvedValueOnce({ ...requirement, submission_status: 'approved', status: 'completed' })
    downloadCompanyRequirementProof.mockRejectedValueOnce(new Error('offline'))
    const wrapper = mountView()
    await flushPromises()

    await wrapper.get('[data-testid="request-changes-requirement-4"]').trigger('click')
    expect(requestCompanyRequirementChanges).not.toHaveBeenCalled()
    expect(wrapper.get('[data-testid="requirement-review-comment-error-4"]').text()).toContain('Add a comment')

    await wrapper.get('#requirement-review-comment-4').setValue('Please include the training date.')
    await wrapper.get('[data-testid="request-changes-requirement-4"]').trigger('click')
    await flushPromises()
    expect(requestCompanyRequirementChanges).toHaveBeenCalledWith(7, 4, { comment: 'Please include the training date.' })

    await wrapper.get('[data-testid="approve-requirement-4"]').trigger('click')
    await flushPromises()
    expect(approveCompanyRequirement).toHaveBeenCalledWith(7, 4, undefined)

    await wrapper.get('[data-testid="download-requirement-proof-4"]').trigger('click')
    await flushPromises()
    expect(downloadCompanyRequirementProof).toHaveBeenCalledWith(7, 4)
    expect(wrapper.get('[role="alert"]').text()).toContain('Unable to reach the API')
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
