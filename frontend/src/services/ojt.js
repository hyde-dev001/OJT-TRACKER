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
export const getInternInternship = () => unwrap(api.get('/intern/internship'))
export const updateInternRequiredHours = (requiredHours) => unwrap(api.put('/intern/internship', { required_hours: Number(requiredHours) }))
export const listCompanyInterns = () => unwrap(api.get('/company/interns'))
export const createCompanyIntern = (payload) => unwrap(api.post('/company/interns', payload))
export const updateCompanyPlacement = (internId, payload) => unwrap(api.put(`/company/interns/${internId}/placement`, payload))
export const resetCompanyInternPassword = (internId, payload) => unwrap(api.post(`/company/interns/${internId}/password-reset`, payload))
export const listCompanyInternTasks = (internId, options = {}) => unwrapPage(api.get(`/company/interns/${internId}/tasks`, { params: pageParams(options) }))
export const createCompanyInternTask = (internId, payload) => unwrap(api.post(`/company/interns/${internId}/tasks`, payload))
export const approveCompanyTask = (internId, taskId, comment) => unwrap(api.post(`/company/interns/${internId}/tasks/${taskId}/approve`, comment ? { comment } : {}))
export const requestCompanyTaskChanges = (internId, taskId, payload) => unwrap(api.post(`/company/interns/${internId}/tasks/${taskId}/request-changes`, payload))
export const downloadCompanyTaskProof = (internId, taskId) => api.get(`/company/interns/${internId}/tasks/${taskId}/proof`, { responseType: 'blob' })
export const listCompanyInternRequirements = (internId, options = {}) => unwrapPage(api.get(`/company/interns/${internId}/requirements`, { params: pageParams(options) }))
export const createCompanyInternRequirement = (internId, payload) => unwrap(api.post(`/company/interns/${internId}/requirements`, payload))
export const approveCompanyRequirement = (internId, requirementId, comment) => unwrap(api.post(`/company/interns/${internId}/requirements/${requirementId}/approve`, comment ? { comment } : {}))
export const requestCompanyRequirementChanges = (internId, requirementId, payload) => unwrap(api.post(`/company/interns/${internId}/requirements/${requirementId}/request-changes`, payload))
export const downloadCompanyRequirementProof = (internId, requirementId) => api.get(`/company/interns/${internId}/requirements/${requirementId}/proof`, { responseType: 'blob' })
export const listCompanyInternWorkLogs = (internId, options = {}) => unwrapPage(api.get(`/company/interns/${internId}/work-logs`, { params: pageParams(options) }))
export const listInternWorkLogs = (options = {}) => unwrapPage(api.get('/intern/work-logs', { params: pageParams(options) }))
export const listInternTasks = (options = {}) => unwrapPage(api.get('/intern/tasks', { params: pageParams(options) }))
export const submitInternTaskProof = (taskId, formData) => unwrap(api.post(`/intern/tasks/${taskId}/submission`, formData))
export const downloadInternTaskProof = (taskId) => api.get(`/intern/tasks/${taskId}/proof`, { responseType: 'blob' })
export const listInternRequirements = (options = {}) => unwrapPage(api.get('/intern/requirements', { params: pageParams(options) }))
export const submitInternRequirementProof = (requirementId, formData) => unwrap(api.post(`/intern/requirements/${requirementId}/submission`, formData))
export const downloadInternRequirementProof = (requirementId) => api.get(`/intern/requirements/${requirementId}/proof`, { responseType: 'blob' })
export const startCompanyWorkLog = (internId) => unwrap(api.post(`/company/interns/${internId}/work-logs/time-in`))
export const endCompanyWorkLog = (internId, payload) => unwrap(api.post(`/company/interns/${internId}/work-logs/time-out`, { break_minutes: Number(payload.break_minutes) }))
export const updateCompanyWorkLog = (internId, workLogId, payload) => unwrap(api.put(`/company/interns/${internId}/work-logs/${workLogId}`, payload))
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
