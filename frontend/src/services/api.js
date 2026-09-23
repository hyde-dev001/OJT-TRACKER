import axios from 'axios'

const baseURL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api'
const backendURL = baseURL.replace(/\/api\/?$/, '')

const api = axios.create({
  baseURL,
  timeout: 5000,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

api.interceptors.response.use(undefined, (error) => {
  if (error?.response?.status === 401 && typeof window !== 'undefined') {
    window.dispatchEvent(new Event('auth:expired'))
  }

  return Promise.reject(error)
})

const csrfClient = axios.create({
  baseURL: backendURL,
  timeout: 5000,
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

export const ensureCsrfCookie = () => csrfClient.get('/sanctum/csrf-cookie')

export function apiErrorMessage(error, fallback = 'Something went wrong. Please try again.') {
  const status = error?.response?.status

  if (status === 401) return 'Your session has expired. Please sign in again.'
  if (status === 403) return 'You do not have permission to perform this action.'
  if (status === 404) return 'The requested record could not be found.'
  if (status === 422) return 'Please correct the highlighted fields.'
  if (status >= 500) return 'The server could not complete that request.'
  if (!error?.response) return 'The API is unavailable. Check the connection and try again.'

  return fallback
}

export default api
