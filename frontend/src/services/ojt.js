import api from './api'

async function unwrap(request) {
  const response = await request
  return response.data?.data ?? response.data
}

async function unwrapPage(request) {
  const response = await request
  const payload = response.data ?? {}

  return {
    items: Array.isArray(payload.data) ? payload.data : [],
    meta: payload.meta ?? null,
    links: payload.links ?? null,
  }
}

function pageParams({ page = 1, filter } = {}) {
  return { page, ...(filter ? { filter } : {}) }
}

export const getStudentInternship = () => unwrap(api.get('/student/internship'))
export const getStudentOverview = () => unwrap(api.get('/student/overview'))
export const exportStudentSummary = () => api.get('/student/overview/export', {
  responseType: 'blob',
  timeout: 90000,
})
export const updateStudentInternship = (requiredHours) => unwrap(api.put('/student/internship', { required_hours: Number(requiredHours) }))
export const listStudentWorkLogs = (options = {}) => unwrapPage(api.get('/student/work-logs', { params: pageParams(options) }))
export const createWorkLog = (payload) => unwrap(api.post('/student/work-logs', payload))
export const getStudentWorkLog = (id) => unwrap(api.get(`/student/work-logs/${id}`))
export const updateWorkLog = (id, payload) => unwrap(api.put(`/student/work-logs/${id}`, payload))
export const deleteWorkLog = (id) => unwrap(api.delete(`/student/work-logs/${id}`))

export const listStudentTasks = (options = {}) => unwrapPage(api.get('/student/tasks', { params: pageParams(options) }))
export const createTask = (payload) => unwrap(api.post('/student/tasks', payload))
export const getStudentTask = (id) => unwrap(api.get(`/student/tasks/${id}`))
export const updateTask = (id, payload) => unwrap(api.put(`/student/tasks/${id}`, payload))
export const deleteTask = (id) => unwrap(api.delete(`/student/tasks/${id}`))
export const startTask = (id) => unwrap(api.post(`/student/tasks/${id}/start`))
export const completeTask = (id) => unwrap(api.post(`/student/tasks/${id}/complete`))

export const listStudentRequirements = (options = {}) => unwrapPage(api.get('/student/requirements', { params: pageParams(options) }))
export const createRequirement = (payload) => unwrap(api.post('/student/requirements', payload))
export const getStudentRequirement = (id) => unwrap(api.get(`/student/requirements/${id}`))
export const updateRequirement = (id, payload) => unwrap(api.put(`/student/requirements/${id}`, payload))
export const deleteRequirement = (id) => unwrap(api.delete(`/student/requirements/${id}`))
export const completeRequirement = (id) => unwrap(api.post(`/student/requirements/${id}/complete`))
export const incompleteRequirement = (id) => unwrap(api.post(`/student/requirements/${id}/incomplete`))
