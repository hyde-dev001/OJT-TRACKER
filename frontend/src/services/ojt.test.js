import { beforeEach, describe, expect, it, vi } from 'vitest'
import api from './api'
import {
  createCompanyIntern,
  createCompanyInternRequirement,
  createCompanyInternTask,
  approveCompanyRequirement,
  approveCompanyTask,
  exportStudentSummary,
  getInternInternship,
  getStudentOverview,
  downloadCompanyRequirementProof,
  downloadCompanyTaskProof,
  downloadInternRequirementProof,
  downloadInternTaskProof,
  listCompanyInternRequirements,
  listCompanyInternTasks,
  listCompanyInterns,
  listCompanyInternWorkLogs,
  listInternWorkLogs,
  listInternRequirements,
  listInternTasks,
  listStudentRequirements,
  listStudentTasks,
  listStudentWorkLogs,
  resetCompanyInternPassword,
  requestCompanyRequirementChanges,
  requestCompanyTaskChanges,
  submitInternRequirementProof,
  submitInternTaskProof,
  updateCompanyPlacement,
  startCompanyWorkLog,
  endCompanyWorkLog,
  updateCompanyWorkLog,
  updateInternRequiredHours,
} from './ojt'

vi.mock('./api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
  },
}))

describe('student OJT service', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('sends the student page', async () => {
    api.get.mockResolvedValue({
      data: {
        data: [{ id: 4, status: 'completed' }],
        links: { next: '/api/student/work-logs?page=2' },
        meta: { current_page: 1, last_page: 2, per_page: 10, total: 11, from: 1, to: 10 },
      },
    })

    await expect(listStudentWorkLogs({ page: 1 })).resolves.toEqual({
      items: [{ id: 4, status: 'completed' }],
      links: { next: '/api/student/work-logs?page=2' },
      meta: { current_page: 1, last_page: 2, per_page: 10, total: 11, from: 1, to: 10 },
    })
    expect(api.get).toHaveBeenCalledWith('/student/work-logs', { params: { page: 1 } })
  })

  it('loads the authenticated student overview', async () => {
    api.get.mockResolvedValue({ data: { data: { progress: { percentage: 48 } } } })

    await expect(getStudentOverview()).resolves.toEqual({ progress: { percentage: 48 } })
    expect(api.get).toHaveBeenCalledWith('/student/overview')
  })

  it('requests the summary as a PDF blob with a Render-friendly timeout', async () => {
    const response = {
      data: new Blob(['%PDF']),
      headers: { 'content-disposition': 'attachment; filename="OJT-Progress-Summary-2026-09-24.pdf"' },
    }
    api.get.mockResolvedValue(response)

    await expect(exportStudentSummary()).resolves.toBe(response)

    expect(api.get).toHaveBeenCalledWith('/student/overview/export', {
      responseType: 'blob',
      timeout: 90000,
    })
  })

  it('sends task pagination and status filter parameters', async () => {
    api.get.mockResolvedValue({
      data: {
        data: [{ id: 4, status: 'completed' }],
        links: {},
        meta: { current_page: 2, last_page: 2, per_page: 10, total: 11, from: 11, to: 11 },
      },
    })

    await expect(listStudentTasks({ page: 2, filter: 'completed' })).resolves.toEqual({
      items: [{ id: 4, status: 'completed' }],
      links: {},
      meta: { current_page: 2, last_page: 2, per_page: 10, total: 11, from: 11, to: 11 },
    })
    expect(api.get).toHaveBeenCalledWith('/student/tasks', { params: { page: 2, filter: 'completed' } })
  })

  it('sends requirements pagination and overdue filter parameters', async () => {
    api.get.mockResolvedValue({ data: { data: [], links: {}, meta: { current_page: 1, last_page: 1, total: 0 } } })

    await listStudentRequirements({ page: 1, filter: 'overdue' })

    expect(api.get).toHaveBeenCalledWith('/student/requirements', { params: { page: 1, filter: 'overdue' } })
  })

  it('uses the centralized API client for company intern management', async () => {
    const payload = {
      first_name: 'Taylor',
      last_name: 'Lee',
      email: 'taylor.lee@example.com',
      password: 'TemporaryPass1!',
      password_confirmation: 'TemporaryPass1!',
      start_date: '2026-11-01',
      end_date: '2027-02-28',
      work_days: [1, 2, 3, 4, 5],
      expected_hours_per_day: 8,
    }
    api.get.mockResolvedValue({ data: { data: [] } })
    api.post.mockResolvedValue({ data: { data: { id: 4 } } })
    api.put.mockResolvedValue({ data: { data: { id: 4 } } })

    await expect(listCompanyInterns()).resolves.toEqual([])
    await createCompanyIntern(payload)
    await updateCompanyPlacement(4, { start_date: '2026-11-02' })
    await resetCompanyInternPassword(4, {
      password: 'NewTemporaryPass1!',
      password_confirmation: 'NewTemporaryPass1!',
    })

    expect(api.get).toHaveBeenCalledWith('/company/interns')
    expect(api.post).toHaveBeenNthCalledWith(1, '/company/interns', payload)
    expect(api.put).toHaveBeenCalledWith('/company/interns/4/placement', { start_date: '2026-11-02' })
    expect(api.post).toHaveBeenNthCalledWith(2, '/company/interns/4/password-reset', {
      password: 'NewTemporaryPass1!',
      password_confirmation: 'NewTemporaryPass1!',
    })
  })

  it('uses the centralized API client for company attendance actions', async () => {
    const page = {
      data: [{ id: 15, status: 'completed' }],
      links: { next: '/api/company/interns/7/work-logs?page=2' },
      meta: { current_page: 1, last_page: 2, per_page: 10, total: 11, from: 1, to: 10 },
    }
    const log = { id: 15, status: 'completed' }
    api.get.mockResolvedValue({ data: page })
    api.post.mockResolvedValue({ data: { data: log } })
    api.put.mockResolvedValue({ data: { data: log } })

    await expect(listCompanyInternWorkLogs(7, { page: 1 })).resolves.toEqual({
      items: page.data,
      links: page.links,
      meta: page.meta,
    })
    await expect(startCompanyWorkLog(7)).resolves.toEqual(log)
    await expect(endCompanyWorkLog(7, { break_minutes: 30 })).resolves.toEqual(log)
    await expect(updateCompanyWorkLog(7, 15, {
      work_date: '2026-10-13',
      time_in: '08:15',
      time_out: '17:00',
      break_minutes: 30,
    })).resolves.toEqual(log)

    expect(api.get).toHaveBeenCalledWith('/company/interns/7/work-logs', { params: { page: 1 } })
    expect(api.post).toHaveBeenNthCalledWith(1, '/company/interns/7/work-logs/time-in')
    expect(api.post).toHaveBeenNthCalledWith(2, '/company/interns/7/work-logs/time-out', { break_minutes: 30 })
    expect(api.put).toHaveBeenCalledWith('/company/interns/7/work-logs/15', {
      work_date: '2026-10-13',
      time_in: '08:15',
      time_out: '17:00',
      break_minutes: 30,
    })
  })

  it('uses the intern endpoints and sends only the required-hours target', async () => {
    api.get.mockResolvedValue({ data: { data: { required_hours: null } } })
    api.put.mockResolvedValue({ data: { data: { required_hours: 500 } } })

    await expect(getInternInternship()).resolves.toEqual({ required_hours: null })
    await expect(updateInternRequiredHours(500)).resolves.toEqual({ required_hours: 500 })

    expect(api.get).toHaveBeenCalledWith('/intern/internship')
    expect(api.put).toHaveBeenCalledWith('/intern/internship', { required_hours: 500 })
  })

  it('loads a paginated read-only intern work-log page', async () => {
    api.get.mockResolvedValue({ data: {
      data: [{ id: 15, status: 'completed' }],
      links: { next: '/api/intern/work-logs?page=2' },
      meta: { current_page: 1, last_page: 2, per_page: 10, total: 11, from: 1, to: 10 },
    } })

    await expect(listInternWorkLogs({ page: 1 })).resolves.toEqual({
      items: [{ id: 15, status: 'completed' }],
      links: { next: '/api/intern/work-logs?page=2' },
      meta: { current_page: 1, last_page: 2, per_page: 10, total: 11, from: 1, to: 10 },
    })
    expect(api.get).toHaveBeenCalledWith('/intern/work-logs', { params: { page: 1 } })
  })

  it('uses company work-item routes and requests private proofs as blobs', async () => {
    const page = { data: [{ id: 8 }], links: {}, meta: { current_page: 1 } }
    const item = { id: 8 }
    const proof = { data: new Blob(['proof']) }
    api.get.mockResolvedValue({ data: page })
    api.post.mockResolvedValue({ data: { data: item } })
    api.get.mockResolvedValueOnce({ data: page })

    await expect(listCompanyInternTasks(4, { page: 2 })).resolves.toEqual({
      items: page.data,
      links: page.links,
      meta: page.meta,
    })
    await expect(listCompanyInternRequirements(4)).resolves.toEqual({
      items: page.data,
      links: page.links,
      meta: page.meta,
    })
    await expect(createCompanyInternTask(4, { title: 'Prepare report' })).resolves.toEqual(item)
    await expect(createCompanyInternRequirement(4, { title: 'Submit form' })).resolves.toEqual(item)
    await approveCompanyTask(4, 8)
    await requestCompanyTaskChanges(4, 8, { comment: 'Add the date.' })
    await approveCompanyRequirement(4, 8)
    await requestCompanyRequirementChanges(4, 8, { comment: 'Add a signature.' })
    api.get.mockResolvedValue(proof)
    await expect(downloadCompanyTaskProof(4, 8)).resolves.toBe(proof)
    await expect(downloadCompanyRequirementProof(4, 8)).resolves.toBe(proof)

    expect(api.get).toHaveBeenCalledWith('/company/interns/4/tasks', { params: { page: 2 } })
    expect(api.get).toHaveBeenCalledWith('/company/interns/4/requirements', { params: { page: 1 } })
    expect(api.post).toHaveBeenCalledWith('/company/interns/4/tasks', { title: 'Prepare report' })
    expect(api.post).toHaveBeenCalledWith('/company/interns/4/requirements', { title: 'Submit form' })
    expect(api.post).toHaveBeenCalledWith('/company/interns/4/tasks/8/approve', {})
    expect(api.post).toHaveBeenCalledWith('/company/interns/4/tasks/8/request-changes', { comment: 'Add the date.' })
    expect(api.post).toHaveBeenCalledWith('/company/interns/4/requirements/8/approve', {})
    expect(api.post).toHaveBeenCalledWith('/company/interns/4/requirements/8/request-changes', { comment: 'Add a signature.' })
    expect(api.get).toHaveBeenCalledWith('/company/interns/4/tasks/8/proof', { responseType: 'blob' })
    expect(api.get).toHaveBeenCalledWith('/company/interns/4/requirements/8/proof', { responseType: 'blob' })
  })

  it('uses intern work-item routes for lists, multipart submissions, and private proof blobs', async () => {
    const page = { data: [{ id: 8 }], links: {}, meta: { current_page: 1 } }
    const formData = new FormData()
    formData.append('comment', 'Proof attached.')
    const item = { id: 8 }
    const proof = { data: new Blob(['proof']) }
    api.get.mockResolvedValue({ data: page })
    api.post.mockResolvedValue({ data: { data: item } })

    await expect(listInternTasks()).resolves.toEqual({ items: page.data, links: page.links, meta: page.meta })
    await expect(listInternRequirements()).resolves.toEqual({ items: page.data, links: page.links, meta: page.meta })
    await expect(submitInternTaskProof(8, formData)).resolves.toEqual(item)
    await expect(submitInternRequirementProof(8, formData)).resolves.toEqual(item)
    api.get.mockResolvedValue(proof)
    await expect(downloadInternTaskProof(8)).resolves.toBe(proof)
    await expect(downloadInternRequirementProof(8)).resolves.toBe(proof)

    expect(api.get).toHaveBeenCalledWith('/intern/tasks', { params: { page: 1 } })
    expect(api.get).toHaveBeenCalledWith('/intern/requirements', { params: { page: 1 } })
    expect(api.post).toHaveBeenCalledWith('/intern/tasks/8/submission', formData)
    expect(api.post).toHaveBeenCalledWith('/intern/requirements/8/submission', formData)
    expect(api.get).toHaveBeenCalledWith('/intern/tasks/8/proof', { responseType: 'blob' })
    expect(api.get).toHaveBeenCalledWith('/intern/requirements/8/proof', { responseType: 'blob' })
  })
})
