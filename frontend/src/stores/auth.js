import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import api, { apiErrorMessage, ensureCsrfCookie } from '../services/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const bootstrapped = ref(false)
  const loading = ref(false)
  const error = ref(null)

  const isAuthenticated = computed(() => Boolean(user.value))

  async function restore() {
    if (bootstrapped.value) return user.value

    error.value = null

    try {
      const response = await api.get('/user')
      user.value = response.data.user
    } catch (requestError) {
      user.value = null

      if (requestError?.response?.status !== 401) {
        error.value = apiErrorMessage(requestError)
      }
    } finally {
      bootstrapped.value = true
    }

    return user.value
  }

  async function login(credentials) {
    loading.value = true
    error.value = null

    try {
      await ensureCsrfCookie()
      const response = await api.post('/login', credentials)
      user.value = response.data.user
      bootstrapped.value = true

      return user.value
    } catch (requestError) {
      error.value = apiErrorMessage(requestError)
      throw requestError
    } finally {
      loading.value = false
    }
  }

  async function register(details) {
    loading.value = true
    error.value = null

    try {
      await ensureCsrfCookie()
      const response = await api.post('/register', details)
      user.value = response.data.user
      bootstrapped.value = true

      return user.value
    } catch (requestError) {
      error.value = apiErrorMessage(requestError)
      throw requestError
    } finally {
      loading.value = false
    }
  }

  async function updateProfile(details) {
    loading.value = true
    error.value = null

    try {
      await ensureCsrfCookie()
      const response = await api.put('/profile', details)
      user.value = response.data.user

      return response.data
    } catch (requestError) {
      error.value = apiErrorMessage(requestError)
      throw requestError
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    loading.value = true
    error.value = null

    try {
      await api.post('/logout')
      user.value = null
      bootstrapped.value = true
      return true
    } catch (requestError) {
      error.value = apiErrorMessage(requestError)
      return false
    } finally {
      loading.value = false
    }
  }

  function clearSession() {
    user.value = null
    bootstrapped.value = true
    error.value = null
  }

  return {
    user,
    bootstrapped,
    loading,
    error,
    isAuthenticated,
    restore,
    login,
    register,
    updateProfile,
    logout,
    clearSession,
  }
})
