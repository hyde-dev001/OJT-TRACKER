import { beforeEach, describe, expect, it, vi } from 'vitest'
import api from './api'
import { exportStudentSummary, getStudentOverview, listStudentRequirements, listStudentTasks, listStudentWorkLogs } from './ojt'

vi.mock('./api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
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
})
